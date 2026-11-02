<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Redirect;
use App\Admin\View;
use App\Core\Database;
use App\Core\Validator;
use App\Services\Entitlements;

/**
 * Account metadata and moderation. Deliberately shows no messages, media, notes,
 * memories, moods or game answers: those belong to the couple.
 */
final class UsersPage
{
    public function index(Ctx $ctx): string
    {
        $q = $ctx->q('q');
        $status = $ctx->q('status');
        $where = [];
        $args = [];
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where[] = '(u.id = ? OR u.phone LIKE ?)';
                array_push($args, (int)$q, '%' . $q);
            } else {
                $where[] = '(u.email LIKE ? OR u.display_name LIKE ? OR u.phone = ?)';
                $like = '%' . addcslashes($q, '%_\\') . '%';
                array_push($args, $like, $like, $q);
            }
        }
        if (in_array($status, ['active', 'suspended', 'banned', 'deleted'], true)) {
            $where[] = 'u.status = ?';
            $args[] = $status;
        }
        $sql = 'FROM users u' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $total = (int)Database::value("SELECT COUNT(*) $sql", $args);
        $off = ($ctx->page() - 1) * View::PER_PAGE;
        $users = Database::all(
            "SELECT u.id, u.display_name, u.email, u.phone, u.status, u.active_couple_id, u.created_at,
                    (SELECT last_seen_at FROM presence p WHERE p.user_id = u.id) AS last_seen,
                    EXISTS(SELECT 1 FROM entitlements e WHERE e.user_id = u.id AND e.entitlement = 'premium' AND e.revoked_at IS NULL) AS premium
             $sql ORDER BY u.id DESC LIMIT " . View::PER_PAGE . " OFFSET $off",
            $args
        );
        return View::render($ctx, 'Users', 'users/index', compact('users', 'total', 'q', 'status'));
    }

    public function show(Ctx $ctx): string
    {
        $u = $this->find($ctx->id());
        $couple = $u['active_couple_id'] ? Database::one(
            'SELECT c.id, c.paired_at, c.status, p.id AS partner_id, p.display_name AS partner_name, p.status AS partner_status
               FROM couples c JOIN users p ON p.id = IF(c.user_a_id = ?, c.user_b_id, c.user_a_id) WHERE c.id = ?',
            [$u['id'], $u['active_couple_id']]
        ) : null;
        $pastCouples = (int)Database::value("SELECT COUNT(*) FROM couples WHERE (user_a_id = ? OR user_b_id = ?) AND status = 'ended'", [$u['id'], $u['id']]);
        $devices = Database::all(
            'SELECT device_name, platform, created_at, last_used_at FROM auth_tokens WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY last_used_at DESC',
            [$u['id']]
        );
        $ents = Database::all('SELECT entitlement, order_id, granted_at, revoked_at FROM entitlements WHERE user_id = ?', [$u['id']]);
        $orders = $ctx->can('payments') ? Database::all(
            'SELECT o.id, o.status, o.amount_paise, o.currency, o.created_at, o.paid_at, p.title FROM orders o JOIN products p ON p.id = o.product_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 20',
            [$u['id']]
        ) : [];
        $reports = $ctx->can('reports') ? Database::all(
            'SELECT id, reporter_id, reported_id, reason, status, created_at FROM reports WHERE reporter_id = ? OR reported_id = ? ORDER BY id DESC LIMIT 20',
            [$u['id'], $u['id']]
        ) : [];
        $history = Database::all(
            "SELECT action, admin_email, details, created_at FROM admin_audit_log WHERE target_type = 'user' AND target_id = ? ORDER BY id DESC LIMIT 20",
            [$u['id']]
        );
        $age = $u['status'] === 'deleted' ? null : Validator::age($u['dob']);
        $lastSeen = Database::value('SELECT last_seen_at FROM presence WHERE user_id = ?', [$u['id']]);
        $effective = Entitlements::forUser($u);
        return View::render($ctx, $u['display_name'], 'users/show', compact('u', 'couple', 'pastCouples', 'devices', 'ents', 'orders', 'reports', 'history', 'age', 'lastSeen', 'effective'));
    }

    public function status(Ctx $ctx): string
    {
        $u = $this->find($ctx->id());
        $action = $ctx->post('action');
        $reason = mb_substr($ctx->post('reason'), 0, 255);
        $map = ['suspend' => 'suspended', 'ban' => 'banned', 'reactivate' => 'active'];
        if (!isset($map[$action]) || $u['status'] === 'deleted') {
            throw new Redirect('/admin/users/' . $u['id'], 'Choose a valid action.', 'error');
        }
        if ($reason === '') {
            throw new Redirect('/admin/users/' . $u['id'], 'Write a short reason. It is kept in the audit log.', 'error');
        }
        self::setStatus($ctx, (int)$u['id'], $map[$action], $reason);
        throw new Redirect('/admin/users/' . $u['id'], 'Account is now ' . $map[$action] . '.');
    }

    /** Also used by report resolution. Suspending or banning signs the user out of every device. */
    public static function setStatus(Ctx $ctx, int $userId, string $status, string $reason): void
    {
        $before = Database::value('SELECT status FROM users WHERE id = ?', [$userId]);
        Database::transaction(function () use ($userId, $status, $reason) {
            Database::run('UPDATE users SET status = ?, status_reason = ? WHERE id = ?', [$status, $status === 'active' ? null : $reason, $userId]);
            if ($status !== 'active') {
                Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
                Database::run('DELETE pt FROM push_tokens pt JOIN auth_tokens t ON t.id = pt.auth_token_id WHERE t.user_id = ?', [$userId]);
            }
        });
        Audit::log($ctx, 'user_' . $status, 'user', $userId, ['from' => $before, 'reason' => $reason]);
    }

    public function revokeSessions(Ctx $ctx): string
    {
        $u = $this->find($ctx->id());
        $n = Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$u['id']])->rowCount();
        Database::run('DELETE pt FROM push_tokens pt JOIN auth_tokens t ON t.id = pt.auth_token_id WHERE t.user_id = ?', [$u['id']]);
        Audit::log($ctx, 'user_sessions_revoked', 'user', (int)$u['id'], ['count' => $n]);
        throw new Redirect('/admin/users/' . $u['id'], "Signed out of $n device(s).");
    }

    /** Manual grant (e.g. support goodwill or a payment made outside the app) or revoke. Always audited. */
    public function entitlement(Ctx $ctx): string
    {
        $u = $this->find($ctx->id());
        $ent = $ctx->post('entitlement');
        $op = $ctx->post('op');
        $reason = mb_substr($ctx->post('reason'), 0, 255);
        if (!in_array($ent, ['premium', 'themes'], true) || !in_array($op, ['grant', 'revoke'], true) || $reason === '') {
            throw new Redirect('/admin/users/' . $u['id'], 'Choose an entitlement and write a reason.', 'error');
        }
        if ($op === 'grant') {
            Entitlements::grant((int)$u['id'], [$ent], null);
        } else {
            Database::run('UPDATE entitlements SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND entitlement = ? AND revoked_at IS NULL', [$u['id'], $ent]);
        }
        Audit::log($ctx, "entitlement_$op", 'user', (int)$u['id'], ['entitlement' => $ent, 'reason' => $reason]);
        throw new Redirect('/admin/users/' . $u['id'], ucfirst($ent) . ($op === 'grant' ? ' granted.' : ' revoked.'));
    }

    private function find(int $id): array
    {
        $u = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) {
            throw new HttpError(404, 'User not found.');
        }
        return $u;
    }
}
