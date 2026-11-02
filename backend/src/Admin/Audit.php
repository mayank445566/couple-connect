<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Database;

final class Audit
{
    public static function log(Ctx $ctx, string $action, ?string $targetType = null, ?int $targetId = null, array $details = []): void
    {
        Database::run(
            'INSERT INTO admin_audit_log (admin_id, admin_email, action, target_type, target_id, details, ip) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $ctx->admin['id'] ?? null,
                $ctx->admin['email'] ?? (string)($details['email'] ?? 'unknown'),
                $action,
                $targetType,
                $targetId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $ctx->req->ip(),
            ]
        );
    }
}
