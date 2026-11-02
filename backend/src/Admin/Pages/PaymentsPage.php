<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Redirect;
use App\Admin\View;
use App\Controllers\StoreController;
use App\Core\ApiException;
use App\Core\Database;
use App\Services\Razorpay;

/** Orders, payments and refunds. Gateway secrets are never shown; amounts come from the server's own records. */
final class PaymentsPage
{
    public function index(Ctx $ctx): string
    {
        $status = $ctx->q('status');
        $q = $ctx->q('q');
        $where = [];
        $args = [];
        if (in_array($status, ['created', 'paid', 'failed', 'refunded'], true)) {
            $where[] = 'o.status = ?';
            $args[] = $status;
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where[] = '(o.id = ? OR o.user_id = ?)';
                array_push($args, (int)$q, (int)$q);
            } else {
                $where[] = '(o.gateway_order_id = ? OR u.email = ? OR EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.gateway_payment_id = ?))';
                array_push($args, $q, strtolower($q), $q);
            }
        }
        $sql = 'FROM orders o JOIN users u ON u.id = o.user_id JOIN products p ON p.id = o.product_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $total = (int)Database::value("SELECT COUNT(*) $sql", $args);
        $off = ($ctx->page() - 1) * View::PER_PAGE;
        $orders = Database::all(
            "SELECT o.*, u.display_name, u.email, p.title $sql ORDER BY o.id DESC LIMIT " . View::PER_PAGE . " OFFSET $off",
            $args
        );
        return View::render($ctx, 'Orders', 'orders/index', compact('orders', 'total', 'status', 'q'));
    }

    public function show(Ctx $ctx): string
    {
        $o = $this->find($ctx->id());
        $payments = Database::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$o['id']]);
        $paymentIds = array_column($payments, 'gateway_payment_id') ?: ['-'];
        $in = implode(',', array_fill(0, count($paymentIds), '?'));
        $events = Database::all(
            "SELECT id, event_id, type, processed_at, created_at FROM webhook_events
              WHERE gateway = ? AND (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.payload.payment.entity.order_id')) = ?
                 OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.payload.order.entity.id')) = ?
                 OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.payload.refund.entity.payment_id')) IN ($in))
              ORDER BY id",
            array_merge([$o['gateway'], $o['gateway_order_id'], $o['gateway_order_id']], $paymentIds)
        );
        $refunder = $o['refunded_by_admin'] ? Database::value('SELECT email FROM admin_users WHERE id = ?', [$o['refunded_by_admin']]) : null;
        return View::render($ctx, 'Order #' . $o['id'], 'orders/show', compact('o', 'payments', 'events', 'refunder'));
    }

    /**
     * Full refund through the gateway, then revoke what the order granted.
     * Idempotent with the refund.processed webhook: whichever arrives first wins.
     */
    public function refund(Ctx $ctx): string
    {
        $o = $this->find($ctx->id());
        $reason = mb_substr($ctx->post('reason'), 0, 255);
        if ($o['status'] !== 'paid') {
            throw new Redirect('/admin/orders/' . $o['id'], 'Only paid orders can be refunded.', 'error');
        }
        if ($reason === '' || !$ctx->checked('confirm')) {
            throw new Redirect('/admin/orders/' . $o['id'], 'Write a reason and tick the confirmation box.', 'error');
        }
        $pay = Database::one("SELECT * FROM payments WHERE order_id = ? AND status IN ('captured','paid') ORDER BY id LIMIT 1", [$o['id']])
            ?? Database::one('SELECT * FROM payments WHERE order_id = ? ORDER BY id LIMIT 1', [$o['id']]);
        if (!$pay) {
            throw new Redirect('/admin/orders/' . $o['id'], 'No payment is recorded for this order.', 'error');
        }
        // Lock first so two admins clicking at once cannot refund twice
        $claimed = Database::run("UPDATE orders SET refunded_by_admin = ? WHERE id = ? AND status = 'paid' AND refunded_by_admin IS NULL", [$ctx->admin['id'], $o['id']])->rowCount();
        if ($claimed === 0) {
            throw new Redirect('/admin/orders/' . $o['id'], 'A refund for this order is already in progress.', 'error');
        }
        try {
            $refundId = $o['gateway'] === 'razorpay'
                ? Razorpay::refund($pay['gateway_payment_id'], (int)$o['amount_paise'], ['order' => (string)$o['id'], 'reason' => mb_substr($reason, 0, 200)])
                : throw new ApiException(400, 'unsupported', 'Refunds for ' . $o['gateway'] . ' are done in that store\'s console.');
        } catch (ApiException $e) {
            Database::run('UPDATE orders SET refunded_by_admin = NULL WHERE id = ?', [$o['id']]);
            Audit::log($ctx, 'refund_failed', 'order', (int)$o['id'], ['error' => $e->getMessage()]);
            throw new Redirect('/admin/orders/' . $o['id'], 'Refund failed: ' . $e->getMessage(), 'error');
        }
        StoreController::refund((int)$o['id']);
        Database::run('UPDATE orders SET refund_gateway_id = ?, refunded_at = COALESCE(refunded_at, UTC_TIMESTAMP()) WHERE id = ?', [$refundId, $o['id']]);
        Audit::log($ctx, 'order_refunded', 'order', (int)$o['id'], ['refund_id' => $refundId, 'amount_paise' => (int)$o['amount_paise'], 'reason' => $reason, 'user' => (int)$o['user_id']]);
        throw new Redirect('/admin/orders/' . $o['id'], 'Refund sent to Razorpay (' . $refundId . '). Premium from this order was removed.');
    }

    public function webhooks(Ctx $ctx): string
    {
        $events = Database::all('SELECT id, event_id, type, processed_at, created_at FROM webhook_events ORDER BY id DESC LIMIT 200');
        return View::render($ctx, 'Webhooks', 'orders/webhooks', compact('events'));
    }

    private function find(int $id): array
    {
        $o = Database::one(
            'SELECT o.*, u.display_name, u.email, p.title, p.sku FROM orders o JOIN users u ON u.id = o.user_id JOIN products p ON p.id = o.product_id WHERE o.id = ?',
            [$id]
        );
        if (!$o) {
            throw new HttpError(404, 'Order not found.');
        }
        return $o;
    }
}
