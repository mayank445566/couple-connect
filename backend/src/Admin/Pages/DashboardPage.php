<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Ctx;
use App\Admin\View;
use App\Core\Database;

/** Counts only. Nothing here reads message bodies or media. */
final class DashboardPage
{
    public function index(Ctx $ctx): string
    {
        $v = fn(string $sql) => (int)Database::value($sql);
        $stats = [
            'Users' => $v("SELECT COUNT(*) FROM users WHERE status <> 'deleted'"),
            'New users (7 days)' => $v("SELECT COUNT(*) FROM users WHERE created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY"),
            'Active couples' => $v("SELECT COUNT(*) FROM couples WHERE status = 'active'"),
            'Active today' => $v("SELECT COUNT(*) FROM presence WHERE last_seen_at > UTC_TIMESTAMP() - INTERVAL 1 DAY"),
            'Messages (24 h)' => $v("SELECT COUNT(*) FROM messages WHERE created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY"),
            'Games played (7 days)' => $v("SELECT COUNT(*) FROM game_sessions WHERE created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY"),
            'Calls (24 h)' => $v("SELECT COUNT(*) FROM calls WHERE created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY"),
            'Suspended or banned' => $v("SELECT COUNT(*) FROM users WHERE status IN ('suspended','banned')"),
        ];
        $rev = null;
        if ($ctx->can('payments')) {
            $rev = Database::one(
                "SELECT COALESCE(SUM(IF(status = 'paid', amount_paise, 0)), 0) AS revenue,
                        SUM(status = 'paid') AS paid, SUM(status = 'refunded') AS refunded,
                        COALESCE(SUM(IF(status = 'paid' AND paid_at > UTC_TIMESTAMP() - INTERVAL 30 DAY, amount_paise, 0)), 0) AS revenue30
                   FROM orders"
            );
            $rev['premium_users'] = $v("SELECT COUNT(DISTINCT user_id) FROM entitlements WHERE entitlement = 'premium' AND revoked_at IS NULL");
            $rev['stuck_webhooks'] = $v('SELECT COUNT(*) FROM webhook_events WHERE processed_at IS NULL AND created_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE');
        }
        $reports = $ctx->can('reports') ? Database::one(
            "SELECT SUM(status IN ('open','reviewing')) AS open, SUM(status IN ('open','reviewing') AND reason = 'underage') AS underage FROM reports"
        ) : null;
        $signups = Database::all(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM users WHERE created_at > UTC_TIMESTAMP() - INTERVAL 14 DAY GROUP BY DATE(created_at) ORDER BY d"
        );
        return View::render($ctx, 'Dashboard', 'dashboard', compact('stats', 'rev', 'reports', 'signups'));
    }
}
