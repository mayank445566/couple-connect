<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Config;

/** Server-side Razorpay integration. Secrets never leave the server. */
final class Razorpay
{
    public static function keyId(): string
    {
        return Config::require('RAZORPAY_KEY_ID');
    }

    /** Creates an order and returns Razorpay's order id. */
    public static function createOrder(int $amountPaise, string $currency, string $receipt, array $notes): string
    {
        if (Config::bool('RAZORPAY_FAKE_ORDERS') && Config::get('APP_ENV') === 'local') {
            return 'order_fake' . bin2hex(random_bytes(7));
        }
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => self::keyId() . ':' . Config::require('RAZORPAY_KEY_SECRET'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['amount' => $amountPaise, 'currency' => $currency, 'receipt' => $receipt, 'notes' => $notes]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || empty($data['id'])) {
            error_log('Razorpay order failed: HTTP ' . $status . ' ' . substr((string)$body, 0, 300));
            throw new ApiException(502, 'payment_gateway_error', 'Could not start the payment. Please try again.');
        }
        if ((int)$data['amount'] !== $amountPaise) {
            throw new ApiException(502, 'payment_gateway_error', 'Payment amount mismatch.');
        }
        return $data['id'];
    }

    /** Full refund of a captured payment. Returns Razorpay's refund id. */
    public static function refund(string $paymentId, int $amountPaise, array $notes = []): string
    {
        if (Config::bool('RAZORPAY_FAKE_ORDERS') && Config::get('APP_ENV') === 'local') {
            return 'rfnd_fake' . bin2hex(random_bytes(7));
        }
        $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => self::keyId() . ':' . Config::require('RAZORPAY_KEY_SECRET'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['amount' => $amountPaise, 'speed' => 'normal', 'notes' => (object)$notes]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || empty($data['id'])) {
            error_log('Razorpay refund failed: HTTP ' . $status . ' ' . substr((string)$body, 0, 300));
            $msg = $data['error']['description'] ?? 'Razorpay did not accept the refund.';
            throw new ApiException(502, 'refund_failed', (string)$msg);
        }
        return (string)$data['id'];
    }

    /** Checkout callback signature: HMAC-SHA256(order_id|payment_id, key_secret). */
    public static function validCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, Config::require('RAZORPAY_KEY_SECRET'));
        return hash_equals($expected, $signature);
    }

    /** Webhook signature: HMAC-SHA256(raw body, webhook_secret). */
    public static function validWebhookSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, Config::require('RAZORPAY_WEBHOOK_SECRET'));
        return hash_equals($expected, $signature);
    }
}
