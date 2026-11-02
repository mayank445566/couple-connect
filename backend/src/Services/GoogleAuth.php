<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * OAuth access tokens for Google service accounts (FCM, Play Developer API).
 * Works on shared hosting: the JWT is signed with openssl, no Google SDK needed. Tokens are cached in storage.
 */
final class GoogleAuth
{
    public static function serviceAccount(string $envKey): array
    {
        $path = Config::get($envKey);
        if (!$path || !is_readable($path)) {
            throw new \RuntimeException("$envKey file is missing or unreadable.");
        }
        $sa = json_decode((string)file_get_contents($path), true);
        if (!isset($sa['client_email'], $sa['private_key'])) {
            throw new \RuntimeException("$envKey is not a service-account JSON file.");
        }
        return $sa;
    }

    public static function token(array $sa, string $scope): string
    {
        $cache = Config::storage('cache') . '/gtoken_' . substr(hash('sha256', $sa['client_email'] . '|' . $scope), 0, 16) . '.json';
        $c = is_readable($cache) ? json_decode((string)file_get_contents($cache), true) : null;
        if ($c && ($c['exp'] ?? 0) > time() + 60) {
            return $c['token'];
        }
        $tokenUrl = self::tokenUrl($sa);
        $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $head = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode(['iss' => $sa['client_email'], 'scope' => $scope, 'aud' => $tokenUrl, 'iat' => $now, 'exp' => $now + 3600]));
        if (!openssl_sign("$head.$claims", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign the Google token request (bad private key?).');
        }
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claims." . $b64($sig)]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $r = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        if (!isset($r['access_token'])) {
            throw new \RuntimeException('Could not get a Google access token: ' . substr(json_encode($r), 0, 200));
        }
        file_put_contents($cache, json_encode(['token' => $r['access_token'], 'exp' => $now + min(3000, (int)($r['expires_in'] ?? 3000))]), LOCK_EX);
        @chmod($cache, 0600);
        return $r['access_token'];
    }

    /** Google's token endpoint. A local test server may be used only when APP_ENV=local. */
    private static function tokenUrl(array $sa): string
    {
        if (Config::devMode() && Config::get('GOOGLE_TEST_BASE')) {
            return rtrim((string)Config::get('GOOGLE_TEST_BASE'), '/') . '/token';
        }
        return 'https://oauth2.googleapis.com/token';
    }
}
