<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Services\Entitlements;
use App\Services\Events;
use App\Services\Present;
use App\Services\Razorpay;

/**
 * One-time purchases. Premium unlocks only after the server verifies the payment,
 * either from the checkout signature or the signed webhook, whichever arrives first.
 * The gateway payment id is unique, so a payment can never be applied twice.
 */
final class StoreController
{
    public function products(Request $req, array $user): array
    {
        $ent = Entitlements::forUser($user);
        $rows = Database::all('SELECT * FROM products WHERE active = 1 ORDER BY price_paise');
        return [
            'products' => array_map(function ($p) use ($ent) {
                $grants = json_decode($p['grants'], true);
                $owned = $grants && !array_filter($grants, fn($g) => !($ent[$g]['owned'] ?? false));
                return [
                    'sku' => $p['sku'],
                    'title' => $p['title'],
                    'description' => $p['description'],
                    'price_paise' => (int)$p['price_paise'],
                    'price_display' => '₹' . number_format($p['price_paise'] / 100, $p['price_paise'] % 100 ? 2 : 0),
                    'currency' => $p['currency'],
                    'grants' => $grants,
                    'one_time' => true,
                    'owned' => $owned,
                ];
            }, $rows),
            'entitlements' => $ent,
        ];
    }

    public function createOrder(Request $req, array $user): array
    {
        RateLimiter::hit('order:user:' . $user['id'], 10, 3600);
        $d = Validator::check($req->json(), ['sku' => 'required,string,max:60']);
        if ($user['email_verified_at'] === null && $user['phone_verified_at'] === null) {
            throw new ApiException(403, 'not_verified', 'Verify your account before purchasing.');
        }
        $p = Database::one('SELECT * FROM products WHERE sku = ? AND active = 1', [$d['sku']]);
        if (!$p) {
            throw new ApiException(404, 'not_found', 'Product not found.');
        }
        $grants = json_decode($p['grants'], true);
        $own = Database::run('SELECT entitlement FROM entitlements WHERE user_id = ? AND revoked_at IS NULL', [$user['id']])->fetchAll(\PDO::FETCH_COLUMN);
        if (!array_diff($grants, $own)) {
            throw new ApiException(409, 'already_owned', 'You already own this. Use Restore purchases on a new device.');
        }
        // Reuse a recent unpaid order so double taps never create two payments
        $order = Database::one(
            "SELECT * FROM orders WHERE user_id = ? AND product_id = ? AND status = 'created' AND created_at > UTC_TIMESTAMP() - INTERVAL 30 MINUTE ORDER BY id DESC LIMIT 1",
            [$user['id'], $p['id']]
        );
        if (!$order) {
            $receipt = 'u' . $user['id'] . '-' . bin2hex(random_bytes(6));
            $gwId = Razorpay::createOrder((int)$p['price_paise'], $p['currency'], $receipt, ['user_id' => (string)$user['id'], 'sku' => $p['sku']]);
            $id = Database::insert(
                'INSERT INTO orders (user_id, product_id, gateway, gateway_order_id, amount_paise, currency) VALUES (?, ?, ?, ?, ?, ?)',
                [$user['id'], $p['id'], 'razorpay', $gwId, $p['price_paise'], $p['currency']]
            );
            $order = Database::one('SELECT * FROM orders WHERE id = ?', [$id]);
        }
        return Router::status(201, [
            'order_id' => (int)$order['id'],
            'gateway' => 'razorpay',
            // Everything the Razorpay checkout SDK needs; only the public key id is sent
            'checkout' => [
                'key' => Razorpay::keyId(),
                'order_id' => $order['gateway_order_id'],
                'amount' => (int)$order['amount_paise'],
                'currency' => $order['currency'],
                'name' => Config::get('APP_NAME', 'Couple Connect'),
                'description' => $p['title'],
                'prefill' => ['email' => $user['email'], 'contact' => $user['phone']],
            ],
        ]);
    }

    /** Called by the app after checkout succeeds. The signature proves Razorpay issued this payment. */
    public function verify(Request $req, array $user): array
    {
        $d = Validator::check($req->json(), [
            'razorpay_order_id' => 'required,string,max:100',
            'razorpay_payment_id' => 'required,string,max:100',
            'razorpay_signature' => 'required,string,max:200',
        ]);
        RateLimiter::hit('verify-pay:user:' . $user['id'], 20, 3600);
        $order = Database::one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)$req->params['id'], $user['id']]);
        if (!$order || $order['gateway_order_id'] !== $d['razorpay_order_id']) {
            throw new ApiException(404, 'not_found', 'Order not found.');
        }
        if (!Razorpay::validCheckoutSignature($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            throw new ApiException(400, 'invalid_signature', 'Payment could not be verified.');
        }
        self::markPaid($order, $d['razorpay_payment_id'], (int)$order['amount_paise'], 'captured', 'client_verify');
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        return ['paid' => true, 'entitlements' => Entitlements::forUser($user)];
    }

    /** POST /webhooks/razorpay. Configure in the Razorpay dashboard with events payment.captured, order.paid, refund.processed. */
    public function webhook(Request $req): array
    {
        $raw = $req->raw();
        $sig = (string)$req->header('x-razorpay-signature');
        if ($sig === '' || !Razorpay::validWebhookSignature($raw, $sig)) {
            throw new ApiException(400, 'invalid_signature', 'Invalid signature.');
        }
        $event = json_decode($raw, true);
        if (!is_array($event) || !isset($event['event'])) {
            throw new ApiException(400, 'bad_payload', 'Invalid payload.');
        }
        $eventId = (string)($req->header('x-razorpay-event-id') ?: hash('sha256', $raw));
        try {
            Database::run('INSERT INTO webhook_events (gateway, event_id, type, payload) VALUES (?, ?, ?, ?)', ['razorpay', $eventId, $event['event'], $raw]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['duplicate' => true]; // Razorpay retries; already handled
            }
            throw $e;
        }
        $payment = $event['payload']['payment']['entity'] ?? null;
        if (in_array($event['event'], ['payment.captured', 'order.paid'], true) && $payment) {
            $order = Database::one("SELECT * FROM orders WHERE gateway = 'razorpay' AND gateway_order_id = ?", [$payment['order_id'] ?? '']);
            if ($order) {
                if ((int)$payment['amount'] !== (int)$order['amount_paise'] || ($payment['currency'] ?? '') !== $order['currency']) {
                    error_log("Razorpay amount mismatch for order {$order['id']}");
                } else {
                    self::markPaid($order, (string)$payment['id'], (int)$payment['amount'], (string)($payment['status'] ?? 'captured'), 'webhook');
                }
            }
        } elseif ($event['event'] === 'refund.processed') {
            $refund = $event['payload']['refund']['entity'] ?? [];
            $pay = Database::one("SELECT p.*, o.user_id, o.product_id FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.gateway = 'razorpay' AND p.gateway_payment_id = ?", [$refund['payment_id'] ?? '']);
            if ($pay) {
                self::refund((int)$pay['order_id']);
            }
        }
        Database::run('UPDATE webhook_events SET processed_at = UTC_TIMESTAMP() WHERE gateway = ? AND event_id = ?', ['razorpay', $eventId]);
        return ['received' => true];
    }

    public function purchases(Request $req, array $user): array
    {
        $rows = Database::all(
            'SELECT o.id, o.status, o.amount_paise, o.currency, o.created_at, o.paid_at, p.sku, p.title,
                    (SELECT gateway_payment_id FROM payments WHERE order_id = o.id ORDER BY id LIMIT 1) AS payment_id
               FROM orders o JOIN products p ON p.id = o.product_id WHERE o.user_id = ? AND o.status <> \'created\' ORDER BY o.id DESC',
            [$user['id']]
        );
        return array_map(fn($r) => [
            'order_id' => (int)$r['id'], 'sku' => $r['sku'], 'title' => $r['title'], 'status' => $r['status'],
            'amount_paise' => (int)$r['amount_paise'], 'currency' => $r['currency'], 'payment_id' => $r['payment_id'],
            'created_at' => Present::iso($r['created_at']), 'paid_at' => Present::iso($r['paid_at']),
        ], $rows);
    }

    /** Restore = read lifetime entitlements for this account; works on any device. */
    public function restore(Request $req, array $user): array
    {
        return ['entitlements' => Entitlements::forUser($user)];
    }

    /** Idempotent: safe to call from both the verify endpoint and the webhook. */
    public static function markPaid(array $order, string $paymentId, int $amount, string $status, string $source): void
    {
        Database::transaction(function () use ($order, $paymentId, $amount, $status, $source) {
            $o = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            Database::run(
                'INSERT IGNORE INTO payments (order_id, gateway, gateway_payment_id, amount_paise, status, source) VALUES (?, ?, ?, ?, ?, ?)',
                [$o['id'], $o['gateway'], $paymentId, $amount, $status, $source]
            );
            if ($o['status'] === 'paid' || $o['status'] === 'refunded') {
                return;
            }
            Database::run("UPDATE orders SET status = 'paid', paid_at = UTC_TIMESTAMP() WHERE id = ?", [$o['id']]);
            $grants = json_decode((string)Database::value('SELECT grants FROM products WHERE id = ?', [$o['product_id']]), true);
            Entitlements::grant((int)$o['user_id'], $grants, (int)$o['id']);
            $coupleId = Database::value('SELECT active_couple_id FROM users WHERE id = ?', [$o['user_id']]);
            if ($coupleId) {
                Events::emit((int)$coupleId, (int)$o['user_id'], 'premium.unlocked', ['grants' => $grants]);
            }
        });
    }

    public static function refund(int $orderId): void
    {
        Database::transaction(function () use ($orderId) {
            $o = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o || $o['status'] === 'refunded') {
                return;
            }
            Database::run("UPDATE orders SET status = 'refunded' WHERE id = ?", [$orderId]);
            Database::run('UPDATE entitlements SET revoked_at = UTC_TIMESTAMP() WHERE order_id = ?', [$orderId]);
        });
    }
}
