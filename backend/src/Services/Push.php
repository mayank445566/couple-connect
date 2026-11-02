<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Push notifications through Firebase Cloud Messaging (HTTP v1).
 *
 * Works on shared hosting: the OAuth token is a JWT signed with openssl from the
 * service-account file, cached on disk for 50 minutes. Sends are queued during the
 * request and flushed after the response is sent, so a slow FCM call never delays the API.
 *
 * PUSH_DRIVER: fcm (production), log (writes storage/logs/push.log), or off.
 */
final class Push
{
    /** @var array<int, array{user_id:int, title:string, body:string, data:array, channel:string}> */
    private static array $queue = [];
    private static bool $hooked = false;

    public static function queue(int $userId, string $title, string $body, array $data, string $channel = 'default'): void
    {
        if (Config::get('PUSH_DRIVER', 'off') === 'off') {
            return;
        }
        self::$queue[] = ['user_id' => $userId, 'title' => $title, 'body' => $body, 'data' => $data, 'channel' => $channel];
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // the app already has its answer
        }
        $queue = self::$queue;
        self::$queue = [];
        foreach ($queue as $p) {
            try {
                self::sendToUser($p);
            } catch (\Throwable $e) {
                error_log('push failed: ' . $e->getMessage());
            }
        }
    }

    private static function sendToUser(array $p): void
    {
        $tokens = Database::all(
            'SELECT pt.id, pt.token FROM push_tokens pt JOIN auth_tokens t ON t.id = pt.auth_token_id
              WHERE pt.user_id = ? AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP()',
            [$p['user_id']]
        );
        $driver = Config::get('PUSH_DRIVER', 'off');
        foreach ($tokens as $t) {
            // FCM data values must be strings
            $data = array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $p['data']);
            if ($driver === 'log') {
                $line = json_encode(['to_user' => $p['user_id'], 'token' => substr($t['token'], 0, 12), 'title' => $p['title'], 'body' => $p['body'], 'channel' => $p['channel'], 'data' => $data]) . "\n";
                file_put_contents(Config::storage('logs') . '/push.log', $line, FILE_APPEND | LOCK_EX);
                continue;
            }
            $status = self::fcm($t['token'], $p['title'], $p['body'], $data, $p['channel']);
            if ($status === 'unregistered') {
                Database::run('DELETE FROM push_tokens WHERE id = ?', [$t['id']]);
            } elseif ($status !== 'ok') {
                Database::run('UPDATE push_tokens SET last_error = ? WHERE id = ?', [substr($status, 0, 100), $t['id']]);
            }
        }
    }

    private static function fcm(string $token, string $title, string $body, array $data, string $channel): string
    {
        $sa = self::serviceAccount();
        $access = self::accessToken($sa);
        $msg = [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'ttl' => $channel === 'calls' ? '45s' : '86400s',
                    'notification' => ['channel_id' => $channel, 'tag' => $data['tag'] ?? null],
                ],
            ],
        ];
        $msg['message']['android']['notification'] = array_filter($msg['message']['android']['notification']);
        $ch = curl_init('https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($msg, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $access],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
        ]);
        $res = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code === 200) {
            return 'ok';
        }
        if ($code === 404 || str_contains($res, 'UNREGISTERED')) {
            return 'unregistered';
        }
        return 'http_' . $code;
    }

    private static function serviceAccount(): array
    {
        $path = Config::get('FCM_SERVICE_ACCOUNT');
        if (!$path || !is_readable($path)) {
            throw new \RuntimeException('FCM_SERVICE_ACCOUNT file is missing or unreadable.');
        }
        $sa = json_decode((string)file_get_contents($path), true);
        if (!isset($sa['client_email'], $sa['private_key'], $sa['project_id'])) {
            throw new \RuntimeException('FCM service account file is invalid.');
        }
        return $sa;
    }

    private static function accessToken(array $sa): string
    {
        $cache = Config::storage('cache') . '/fcm_token.json';
        $c = is_readable($cache) ? json_decode((string)file_get_contents($cache), true) : null;
        if ($c && ($c['exp'] ?? 0) > time() + 60) {
            return $c['token'];
        }
        $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $head = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        openssl_sign("$head.$claims", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claims." . $b64($sig)]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
        ]);
        $r = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        if (!isset($r['access_token'])) {
            throw new \RuntimeException('Could not get an FCM access token.');
        }
        @mkdir(dirname($cache), 0700, true);
        file_put_contents($cache, json_encode(['token' => $r['access_token'], 'exp' => $now + 3000]), LOCK_EX);
        @chmod($cache, 0600);
        return $r['access_token'];
    }
}
