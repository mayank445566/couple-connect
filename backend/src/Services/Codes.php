<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;

/** Six-digit one-time codes for email verification and password reset. */
final class Codes
{
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, Config::key('APP_KEY'));
    }

    public static function sixDigits(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Creates a new code (invalidating older ones for the same purpose) and returns it. */
    public static function create(int $userId, string $purpose): string
    {
        $code = self::sixDigits();
        Database::run('UPDATE user_codes SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$userId, $purpose]);
        Database::run(
            'INSERT INTO user_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
            [$userId, $purpose, self::hmac($purpose . ':' . $code), self::TTL_MINUTES]
        );
        return $code;
    }

    /** Consumes a code. Throws a generic error on any mismatch so codes cannot be probed. */
    public static function consume(int $userId, string $purpose, string $code): void
    {
        $row = Database::one(
            'SELECT id, code_hash, attempts FROM user_codes
              WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
              ORDER BY id DESC LIMIT 1',
            [$userId, $purpose]
        );
        $invalid = new ApiException(400, 'invalid_code', 'That code is invalid or has expired.');
        if ($row === null || (int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            throw $invalid;
        }
        if (!hash_equals($row['code_hash'], self::hmac($purpose . ':' . $code))) {
            Database::run('UPDATE user_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            throw $invalid;
        }
        Database::run('UPDATE user_codes SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
    }
}
