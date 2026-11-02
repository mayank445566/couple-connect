<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Ctx;
use App\Admin\View;
use App\Core\Database;

/** Read-only. The panel has no way to edit or delete audit entries. */
final class AuditPage
{
    public function index(Ctx $ctx): string
    {
        $action = $ctx->q('action');
        $admin = $ctx->q('admin');
        $type = $ctx->q('type');
        $where = [];
        $args = [];
        if ($action !== '') {
            $where[] = 'action LIKE ?';
            $args[] = addcslashes($action, '%_\\') . '%';
        }
        if ($admin !== '') {
            $where[] = 'admin_email = ?';
            $args[] = strtolower($admin);
        }
        if ($type !== '') {
            $where[] = 'target_type = ?';
            $args[] = $type;
            if (ctype_digit($ctx->q('id'))) {
                $where[] = 'target_id = ?';
                $args[] = (int)$ctx->q('id');
            }
        }
        $sql = 'FROM admin_audit_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $total = (int)Database::value("SELECT COUNT(*) $sql", $args);
        $off = ($ctx->page() - 1) * View::PER_PAGE;
        $rows = Database::all("SELECT * $sql ORDER BY id DESC LIMIT " . View::PER_PAGE . " OFFSET $off", $args);
        return View::render($ctx, 'Audit log', 'audit', compact('rows', 'total', 'action', 'admin', 'type'));
    }
}
