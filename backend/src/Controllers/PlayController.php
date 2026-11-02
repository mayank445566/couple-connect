<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Validator;
use App\Services\Entitlements;
use App\Services\GooglePlay;

/**
 * Google Play Billing for the Play Store build (Play requires it for digital goods).
 * Same rules as Razorpay: Premium unlocks only after the server verifies the purchase with Google,
 * one Google order can be applied once, and refunds/voids revoke it.
 */
final class PlayController
{
    /** What the app needs to start a Play purchase. */
    public function config(Request $req, array $user): array
    {
        $p = Database::one("SELECT sku, play_product_id FROM products WHERE active = 1 AND play_product_id IS NOT NULL ORDER BY price_paise LIMIT 1");
        return [
            'enabled' => GooglePlay::enabled(),
            'products' => $p ? [['sku' => $p['sku'], 'play_product_id' => $p['play_product_id']]] : [],
            'account_tag' => GooglePlay::accountTag((int)$user['id']),
        ];
    }

    /** POST /store/google/verify {sku, purchase_token}. Idempotent; safe to call again from Restore. */
    public function verify(Request $req, array $user): array
    {
        RateLimiter::hit('play-verify:user:' . $user['id'], 30, 3600);
        $d = Validator::check($req->json(), ['sku' => 'required,string,max:60', 'purchase_token' => 'required,string,max:1000']);
        if (!GooglePlay::enabled()) {
            throw new ApiException(503, 'play_disabled', 'Google Play purchases are not set up on this server.');
        }
        $p = Database::one('SELECT * FROM products WHERE sku = ? AND play_product_id IS NOT NULL', [$d['sku']]);
        if (!$p) {
            throw new ApiException(404, 'not_found', 'Product not found.');
        }
        $purchase = GooglePlay::getPurchase($p['play_product_id'], $d['purchase_token']);
        $owner = GooglePlay::userFromTag($purchase['obfuscatedExternalAccountId'] ?? null);
        if ($owner !== (int)$user['id']) {
            throw new ApiException(403, 'purchase_not_yours', 'This purchase belongs to a different account.');
        }
        $state = (int)($purchase['purchaseState'] ?? -1);
        if ($state === 2) {
            return ['paid' => false, 'pending' => true, 'message' => 'Payment is pending. Premium unlocks as soon as Google confirms it.'];
        }
        if ($state !== 0) {
            throw new ApiException(402, 'purchase_cancelled', 'This purchase was cancelled.');
        }
        self::apply($p, $purchase, $d['purchase_token'], 'client_verify');
        $fresh = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        return ['paid' => true, 'pending' => false, 'entitlements' => Entitlements::forUser($fresh)];
    }

    /**
     * POST /webhooks/google-play?token=PLAY_RTDN_TOKEN — Real-time developer notifications via Pub/Sub push.
     * Handles completed pending purchases and voided (refunded / charged back) purchases.
     */
    public function webhook(Request $req): array
    {
        $expected = (string)Config::get('PLAY_RTDN_TOKEN', '');
        if ($expected === '' || !hash_equals($expected, (string)($req->query['token'] ?? ''))) {
            throw new ApiException(401, 'unauthenticated', 'Bad token.');
        }
        $msg = $req->json()['message'] ?? null;
        $data = is_array($msg) ? json_decode((string)base64_decode((string)($msg['data'] ?? ''), true), true) : null;
        if (!is_array($data)) {
            return ['ignored' => true];
        }
        if (($data['packageName'] ?? '') !== Config::get('PLAY_PACKAGE_NAME')) {
            return ['ignored' => true];
        }
        $eventId = (string)($msg['messageId'] ?? $msg['message_id'] ?? hash('sha256', (string)$msg['data']));
        try {
            Database::run('INSERT INTO webhook_events (gateway, event_id, type, payload) VALUES (?, ?, ?, ?)', ['google', $eventId, (string)array_key_first(array_diff_key($data, array_flip(['version', 'packageName', 'eventTimeMillis']))), json_encode($data)]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['duplicate' => true];
            }
            throw $e;
        }
        if (isset($data['voidedPurchaseNotification'])) {
            $v = $data['voidedPurchaseNotification'];
            $orderId = Database::value("SELECT id FROM orders WHERE gateway = 'google' AND gateway_order_id = ?", [(string)($v['orderId'] ?? '')]);
            if ($orderId) {
                StoreController::refund((int)$orderId);
            }
        } elseif (isset($data['oneTimeProductNotification'])) {
            $n = $data['oneTimeProductNotification'];
            // 1 = ONE_TIME_PRODUCT_PURCHASED (also sent when a pending payment completes)
            if ((int)($n['notificationType'] ?? 0) === 1) {
                $p = Database::one('SELECT * FROM products WHERE play_product_id = ?', [(string)($n['sku'] ?? '')]);
                if ($p) {
                    $purchase = GooglePlay::getPurchase($p['play_product_id'], (string)$n['purchaseToken']);
                    if ((int)($purchase['purchaseState'] ?? -1) === 0 && GooglePlay::userFromTag($purchase['obfuscatedExternalAccountId'] ?? null) !== null) {
                        self::apply($p, $purchase, (string)$n['purchaseToken'], 'webhook');
                    }
                }
            }
        }
        Database::run("UPDATE webhook_events SET processed_at = UTC_TIMESTAMP() WHERE gateway = 'google' AND event_id = ?", [$eventId]);
        return ['received' => true];
    }

    /** Records the Google order once and grants Premium through the same idempotent path as Razorpay. */
    private static function apply(array $product, array $purchase, string $token, string $source): void
    {
        $userId = GooglePlay::userFromTag($purchase['obfuscatedExternalAccountId'] ?? null);
        $gOrder = (string)($purchase['orderId'] ?? '');
        if ($userId === null || $gOrder === '') {
            throw new ApiException(400, 'invalid_purchase', 'Purchase is missing its order id.');
        }
        Database::run(
            "INSERT IGNORE INTO orders (user_id, product_id, gateway, gateway_order_id, amount_paise, currency) VALUES (?, ?, 'google', ?, ?, ?)",
            [$userId, $product['id'], $gOrder, $product['price_paise'], $product['currency']]
        );
        $order = Database::one("SELECT * FROM orders WHERE gateway = 'google' AND gateway_order_id = ?", [$gOrder]);
        if ((int)$order['user_id'] !== $userId) {
            throw new ApiException(403, 'purchase_not_yours', 'This purchase belongs to a different account.');
        }
        // amount_paise records the list price; Play may charge a local price, which shows in the Play Console
        StoreController::markPaid($order, $gOrder, (int)$order['amount_paise'], 'purchased', $source);
        if ((int)($purchase['acknowledgementState'] ?? 0) === 0) {
            GooglePlay::acknowledge($product['play_product_id'], $token);
        }
    }
}
