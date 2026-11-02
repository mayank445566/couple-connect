<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\MediaOut;
use App\Admin\Redirect;
use App\Admin\Totp;
use App\Admin\View;
use App\Core\Database;

/**
 * Audited case access. An owner opens ONE couple's chat for a stated case:
 * a fresh two-step code, a basis and reference, a written reason, 24 hours max.
 * Every page of messages and every media file opened is written to the audit log.
 * Deleted and expired (disappearing) messages are gone and cannot be recovered here.
 */
final class CasesPage
{
    public const HOURS = 24;
    private const PAGE = 100;

    public function index(Ctx $ctx): string
    {
        $cases = Database::all(
            'SELECT g.*, a.email AS admin_email, (g.closed_at IS NULL AND g.expires_at > UTC_TIMESTAMP()) AS active
               FROM case_access_grants g JOIN admin_users a ON a.id = g.admin_id ORDER BY g.id DESC LIMIT 200'
        );
        $couple = null;
        $cid = (int)$ctx->q('couple');
        if ($cid > 0) {
            $couple = $this->couple($cid);
        }
        $reportId = (int)$ctx->q('report');
        return View::render($ctx, 'Case access', 'cases/index', ['cases' => $cases, 'couple' => $couple, 'reportId' => $reportId ?: '', 'has2fa' => $ctx->admin['totp_enabled_at'] !== null]);
    }

    public function open(Ctx $ctx): string
    {
        $coupleId = (int)$ctx->post('couple_id');
        $back = '/admin/cases?couple=' . $coupleId;
        $basis = $ctx->post('basis');
        $reference = mb_substr($ctx->post('reference'), 0, 100);
        $reason = mb_substr($ctx->post('reason'), 0, 1000);
        $a = Database::one('SELECT totp_secret, totp_enabled_at, totp_last_step FROM admin_users WHERE id = ?', [$ctx->admin['id']]);
        if ($a['totp_enabled_at'] === null) {
            throw new Redirect('/admin/account', 'Turn on two-step sign-in before using case access.', 'error');
        }
        $this->couple($coupleId);
        if (!in_array($basis, ['user_report', 'suspected_illegal', 'legal_request', 'safety_emergency'], true) || $reference === '' || mb_strlen($reason) < 20) {
            throw new Redirect($back, 'Choose a basis, give a reference and write a reason of at least 20 characters.', 'error');
        }
        if ($basis === 'user_report') {
            $ok = Database::value('SELECT 1 FROM reports r WHERE r.id = ? AND (r.couple_id = ? OR r.reporter_id IN (SELECT user_a_id FROM couples WHERE id = ? UNION SELECT user_b_id FROM couples WHERE id = ?))', [(int)$reference, $coupleId, $coupleId, $coupleId]);
            if (!$ok) {
                throw new Redirect($back, 'For a user report, the reference must be the number of a report filed by someone in this couple.', 'error');
            }
        }
        $step = Totp::verify(Totp::open($a['totp_secret']), $ctx->post('code'), $a['totp_last_step'] !== null ? (int)$a['totp_last_step'] : null);
        if ($step === null) {
            Audit::log($ctx, 'case_access_denied', 'couple', $coupleId, ['why' => 'bad 2fa code']);
            throw new Redirect($back, 'That two-step code is not valid.', 'error');
        }
        Database::run('UPDATE admin_users SET totp_last_step = ? WHERE id = ?', [$step, $ctx->admin['id']]);
        $id = Database::insert(
            'INSERT INTO case_access_grants (admin_id, couple_id, basis, reference, reason, expires_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? HOUR)',
            [$ctx->admin['id'], $coupleId, $basis, $reference, $reason, self::HOURS]
        );
        Audit::log($ctx, 'case_access_opened', 'couple', $coupleId, ['case' => $id, 'basis' => $basis, 'reference' => $reference, 'reason' => $reason]);
        throw new Redirect('/admin/cases/' . $id, 'Case access is open for ' . self::HOURS . ' hours. Every page you view is logged.');
    }

    public function show(Ctx $ctx): string
    {
        $g = $this->grant($ctx);
        $before = (int)$ctx->q('before');
        $rows = Database::all(
            'SELECT m.id, m.sender_id, m.type, m.body, m.media_id, m.created_at, m.edited_at, m.deleted_at, m.expires_at, u.display_name AS sender
               FROM messages m JOIN users u ON u.id = m.sender_id
              WHERE m.couple_id = ?' . ($before > 0 ? ' AND m.id < ?' : '') . ' ORDER BY m.id DESC LIMIT ' . self::PAGE,
            $before > 0 ? [$g['couple_id'], $before] : [$g['couple_id']]
        );
        $media = [];
        $ids = array_values(array_filter(array_map(fn($r) => $r['media_id'] ? (int)$r['media_id'] : null, $rows)));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::all("SELECT id, kind, mime, size_bytes, deleted_at FROM media WHERE couple_id = ? AND id IN ($in)", array_merge([$g['couple_id']], $ids)) as $m) {
                $media[(int)$m['id']] = $m;
            }
        }
        if ($rows) {
            Audit::log($ctx, 'case_messages_viewed', 'couple', (int)$g['couple_id'], ['case' => (int)$g['id'], 'from_id' => (int)end($rows)['id'], 'to_id' => (int)$rows[0]['id'], 'count' => count($rows)]);
        }
        $couple = $this->couple((int)$g['couple_id']);
        $next = count($rows) === self::PAGE ? (int)end($rows)['id'] : null;
        return View::render($ctx, 'Case #' . $g['id'], 'cases/show', ['g' => $g, 'rows' => $rows, 'media' => $media, 'couple' => $couple, 'next' => $next]);
    }

    public function media(Ctx $ctx): never
    {
        $g = $this->grant($ctx);
        $m = Database::one('SELECT * FROM media WHERE id = ? AND couple_id = ? AND deleted_at IS NULL', [$ctx->id('mediaId'), $g['couple_id']]);
        if (!$m) {
            throw new HttpError(404, 'This file was deleted or does not belong to this case.');
        }
        Audit::log($ctx, 'case_media_viewed', 'couple', (int)$g['couple_id'], ['case' => (int)$g['id'], 'media' => (int)$m['id'], 'kind' => $m['kind']]);
        MediaOut::send($m);
    }

    public function close(Ctx $ctx): string
    {
        $g = $this->grant($ctx);
        Database::run('UPDATE case_access_grants SET closed_at = UTC_TIMESTAMP() WHERE id = ?', [$g['id']]);
        Audit::log($ctx, 'case_access_closed', 'couple', (int)$g['couple_id'], ['case' => (int)$g['id']]);
        throw new Redirect('/admin/cases', 'Case access closed.');
    }

    /** Only the owner who opened the case, only while it is open. */
    private function grant(Ctx $ctx): array
    {
        $g = Database::one('SELECT * FROM case_access_grants WHERE id = ? AND admin_id = ? AND closed_at IS NULL AND expires_at > UTC_TIMESTAMP()', [$ctx->id(), $ctx->admin['id']]);
        if (!$g) {
            throw new HttpError(403, 'This case is closed, expired, or was opened by another admin. Open a new case to look again.');
        }
        return $g;
    }

    private function couple(int $id): array
    {
        $c = Database::one(
            'SELECT c.*, a.display_name AS a_name, b.display_name AS b_name FROM couples c JOIN users a ON a.id = c.user_a_id JOIN users b ON b.id = c.user_b_id WHERE c.id = ?',
            [$id]
        );
        if (!$c) {
            throw new HttpError(404, 'Couple not found.');
        }
        return $c;
    }
}
