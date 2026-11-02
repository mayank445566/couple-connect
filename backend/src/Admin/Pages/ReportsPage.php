<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\MediaOut;
use App\Admin\Redirect;
use App\Admin\View;
use App\Core\Database;

/** Reports from users. Moderators see only the messages the reporter attached (a snapshot taken when reporting). */
final class ReportsPage
{
    public function index(Ctx $ctx): string
    {
        $status = $ctx->q('status', 'open');
        $where = match ($status) {
            'open' => "r.status IN ('open','reviewing')",
            'resolved', 'dismissed' => 'r.status = ' . Database::pdo()->quote($status),
            default => '1',
        };
        $total = (int)Database::value("SELECT COUNT(*) FROM reports r WHERE $where");
        $off = ($ctx->page() - 1) * View::PER_PAGE;
        $reports = Database::all(
            "SELECT r.id, r.reason, r.status, r.created_at, r.reporter_id, r.reported_id, JSON_LENGTH(r.attached) AS n_attached,
                    rep.display_name AS reporter_name, tgt.display_name AS reported_name
               FROM reports r JOIN users rep ON rep.id = r.reporter_id LEFT JOIN users tgt ON tgt.id = r.reported_id
              WHERE $where ORDER BY (r.status IN ('open','reviewing') AND r.reason = 'underage') DESC, r.id " . ($status === 'open' ? 'ASC' : 'DESC') .
            ' LIMIT ' . View::PER_PAGE . " OFFSET $off"
        );
        return View::render($ctx, 'Reports', 'reports/index', compact('reports', 'total', 'status'));
    }

    public function show(Ctx $ctx): string
    {
        $r = $this->find($ctx->id());
        $attached = json_decode((string)($r['attached'] ?? '[]'), true) ?: [];
        $names = [];
        foreach (Database::all('SELECT id, display_name, status FROM users WHERE id IN (?, ?)', [$r['reporter_id'], $r['reported_id'] ?? 0]) as $u) {
            $names[(int)$u['id']] = $u;
        }
        $mediaIds = array_values(array_filter(array_map(fn($m) => $m['media_id'] ?? null, $attached)));
        $media = [];
        if ($mediaIds) {
            $in = implode(',', array_fill(0, count($mediaIds), '?'));
            foreach (Database::all("SELECT id, kind, mime, size_bytes, deleted_at FROM media WHERE id IN ($in)", $mediaIds) as $m) {
                $media[(int)$m['id']] = $m;
            }
        }
        $handler = $r['handled_by'] ? Database::value('SELECT email FROM admin_users WHERE id = ?', [$r['handled_by']]) : null;
        $prior = $r['reported_id'] ? (int)Database::value('SELECT COUNT(*) FROM reports WHERE reported_id = ? AND id <> ?', [$r['reported_id'], $r['id']]) : 0;
        if ($r['status'] === 'open') {
            Database::run("UPDATE reports SET status = 'reviewing' WHERE id = ? AND status = 'open'", [$r['id']]);
            $r['status'] = 'reviewing';
        }
        Audit::log($ctx, 'report_viewed', 'report', (int)$r['id']);
        return View::render($ctx, 'Report #' . $r['id'], 'reports/show', compact('r', 'attached', 'names', 'media', 'handler', 'prior'));
    }

    /** Streams a file only if it is attached to this report. */
    public function media(Ctx $ctx): never
    {
        $r = $this->find($ctx->id());
        $mediaId = $ctx->id('mediaId');
        $attached = json_decode((string)($r['attached'] ?? '[]'), true) ?: [];
        if (!in_array($mediaId, array_map(fn($m) => (int)($m['media_id'] ?? 0), $attached), true)) {
            throw new HttpError(404, 'This file is not attached to the report.');
        }
        $m = Database::one('SELECT * FROM media WHERE id = ? AND deleted_at IS NULL', [$mediaId]);
        if (!$m) {
            throw new HttpError(404, 'The sender deleted this file.');
        }
        Audit::log($ctx, 'report_media_viewed', 'report', (int)$r['id'], ['media' => $mediaId]);
        MediaOut::send($m);
    }

    public function update(Ctx $ctx): string
    {
        $r = $this->find($ctx->id());
        $status = $ctx->post('status');
        $action = $ctx->post('action', 'none');
        $note = mb_substr($ctx->post('note'), 0, 1000);
        if (!in_array($status, ['reviewing', 'resolved', 'dismissed'], true) || !in_array($action, ['none', 'suspended', 'banned'], true)) {
            throw new Redirect('/admin/reports/' . $r['id'], 'Choose a valid outcome.', 'error');
        }
        if ($status !== 'reviewing' && $note === '') {
            throw new Redirect('/admin/reports/' . $r['id'], 'Write a short note on what you decided and why.', 'error');
        }
        if ($action !== 'none') {
            if (!$ctx->can('users.moderate') || !$r['reported_id']) {
                throw new Redirect('/admin/reports/' . $r['id'], 'This report has no reported account to act on.', 'error');
            }
            UsersPage::setStatus($ctx, (int)$r['reported_id'], $action, "Report #{$r['id']}: " . mb_substr($note, 0, 200));
        }
        Database::run(
            'UPDATE reports SET status = ?, action_taken = ?, resolution_note = ?, handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $action, $note !== '' ? $note : null, $ctx->admin['id'], $r['id']]
        );
        Audit::log($ctx, 'report_' . $status, 'report', (int)$r['id'], ['action' => $action, 'note' => $note]);
        throw new Redirect($status === 'reviewing' ? '/admin/reports/' . $r['id'] : '/admin/reports', "Report #{$r['id']} saved.");
    }

    private function find(int $id): array
    {
        $r = Database::one('SELECT * FROM reports WHERE id = ?', [$id]);
        if (!$r) {
            throw new HttpError(404, 'Report not found.');
        }
        return $r;
    }
}
