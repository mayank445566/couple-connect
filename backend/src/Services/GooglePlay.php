<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Config;

/**
 * Google Play Developer API for one-time products (Play Billing).
 * The app only hands over a purchase token; the server asks Google whether it is real.
 */
final class GooglePlay
{
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public static function enabled(): bool
    {
        return Config::get('PLAY_PACKAGE_NAME') !== null && Config::get('PLAY_SERVICE_ACCOUNT') !== null;
    }

    /** Opaque id the app passes to Play at purchase time, so a token can only be redeemed by the account that bought it. */
    public static function accountTag(int $userId): string
    {
        return 'u' . $userId . '.' . substr(Codes::hmac('play-account:' . $userId), 0, 24);
    }

    public static function userFromTag(?string $tag): ?int
    {
        if (!$tag || !preg_match('/^u(\d+)\.[a-f0-9]{24}$/', $tag, $m)) {
            return null;
        }
        return hash_equals(self::accountTag((int)$m[1]), $tag) ? (int)$m[1] : null;
    }

    /**
     * purchases.products.get. Returns Google's purchase resource:
     * purchaseState (0 purchased, 1 cancelled, 2 pending), acknowledgementState, orderId, obfuscatedExternalAccountId.
     */
    public static function getPurchase(string $productId, string $token): array
    {
        [$code, $body] = self::call('GET', self::path($productId, $token));
        if ($code === 404 || $code === 400 || $code === 410) {
            throw new ApiException(400, 'invalid_purchase', 'Google Play did not recognise this purchase.');
        }
        if ($code !== 200 || !is_array($body)) {
            error_log('Play purchases.products.get HTTP ' . $code . ' ' . substr(json_encode($body), 0, 300));
            throw new ApiException(502, 'play_unavailable', 'Could not reach Google Play. Your purchase is safe; try Restore in a minute.');
        }
        return $body;
    }

    /** Must be called within 3 days or Google refunds the purchase automatically. */
    public static function acknowledge(string $productId, string $token): void
    {
        [$code] = self::call('POST', self::path($productId, $token) . ':acknowledge', []);
        if ($code !== 200 && $code !== 204) {
            error_log("Play acknowledge failed HTTP $code for $productId");
        }
    }

    private static function path(string $productId, string $token): string
    {
        return '/androidpublisher/v3/applications/' . rawurlencode((string)Config::require('PLAY_PACKAGE_NAME'))
            . '/purchases/products/' . rawurlencode($productId) . '/tokens/' . rawurlencode($token);
    }

    private static function call(string $method, string $path, ?array $json = null): array
    {
        $sa = GoogleAuth::serviceAccount('PLAY_SERVICE_ACCOUNT');
        $base = Config::devMode() && Config::get('GOOGLE_TEST_BASE') ? rtrim((string)Config::get('GOOGLE_TEST_BASE'), '/') : 'https://androidpublisher.googleapis.com';
        $ch = curl_init($base . $path);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . GoogleAuth::token($sa, self::SCOPE), 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode((object)$json);
        }
        curl_setopt_array($ch, $opts);
        $raw = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$code, json_decode($raw, true)];
    }
}
