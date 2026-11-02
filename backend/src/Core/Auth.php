<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Issues a device token. Returns [plainToken, tokenId]. */
    public static function issue(int $userId, Request $req, ?string $deviceName, ?string $platform): array
    {
        $token = self::newToken();
        $days = Config::int('TOKEN_TTL_DAYS', 90);
        $id = Database::insert(
            'INSERT INTO auth_tokens (user_id, token_hash, device_name, platform, ip, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? DAY)',
            [
                $userId,
                self::hashToken($token),
                $deviceName !== null ? mb_substr($deviceName, 0, 100) : null,
                $platform !== null ? mb_substr($platform, 0, 20) : null,
                $req->ip(),
                mb_substr((string)$req->header('user-agent'), 0, 255),
                $days,
            ]
        );
        return [$token, $id];
    }

    /** Resolves the bearer token to an active user, or throws 401. Returns user row + token_id. */
    public static function require(Request $req): array
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new ApiException(401, 'unauthenticated', 'Sign in required.');
        }
        $row = Database::one(
            'SELECT u.*, t.id AS token_id, t.last_used_at AS token_last_used
               FROM auth_tokens t JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP()',
            [self::hashToken($token)]
        );
        if ($row === null) {
            throw new ApiException(401, 'unauthenticated', 'Session expired. Please sign in again.');
        }
        if ($row['status'] !== 'active') {
            throw new ApiException(403, 'account_' . $row['status'], 'This account is ' . $row['status'] . '.');
        }
        // Sliding expiry, written at most every 5 minutes to keep writes low
        if (strtotime($row['token_last_used'] . ' UTC') < time() - 300) {
            Database::run(
                'UPDATE auth_tokens SET last_used_at = UTC_TIMESTAMP(), ip = ?,
                        expires_at = UTC_TIMESTAMP() + INTERVAL ? DAY WHERE id = ?',
                [$req->ip(), Config::int('TOKEN_TTL_DAYS', 90), $row['token_id']]
            );
        }
        // Any authenticated request counts as activity for the partner's online indicator
        Database::run(
            'INSERT INTO presence (user_id, last_seen_at) VALUES (?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP()',
            [$row['id']]
        );
        unset($row['password_hash'], $row['token_last_used']);
        return $row;
    }

    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($password, $algo);
    }
}
