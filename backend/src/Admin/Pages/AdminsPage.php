<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Redirect;
use App\Admin\View;
use App\Core\Auth;
use App\Core\Database;

final class AdminsPage
{
    public function index(Ctx $ctx): string
    {
        $admins = Database::all('SELECT id, email, name, role, status, totp_enabled_at, last_login_at, locked_until, created_at FROM admin_users ORDER BY id');
        return View::render($ctx, 'Admins', 'admins', ['admins' => $admins, 'roles' => Ctx::ROLE_LABELS, 'temp' => null]);
    }

    public function create(Ctx $ctx): string
    {
        $email = strtolower($ctx->post('email'));
        $name = mb_substr($ctx->post('name'), 0, 60);
        $role = $ctx->post('role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !isset(Ctx::ROLES[$role])) {
            throw new Redirect('/admin/admins', 'Enter an email, a name and a role.', 'error');
        }
        if (Database::value('SELECT 1 FROM admin_users WHERE email = ?', [$email])) {
            throw new Redirect('/admin/admins', 'An admin with that email already exists.', 'error');
        }
        $temp = self::tempPassword();
        $id = Database::insert(
            'INSERT INTO admin_users (email, name, password_hash, role, must_change_password, created_by) VALUES (?, ?, ?, ?, 1, ?)',
            [$email, $name, Auth::hashPassword($temp), $role, $ctx->admin['id']]
        );
        Audit::log($ctx, 'admin_created', 'admin', $id, ['email' => $email, 'role' => $role]);
        // Shown once on this response, never stored in plain text or sent by email
        $admins = Database::all('SELECT id, email, name, role, status, totp_enabled_at, last_login_at, locked_until, created_at FROM admin_users ORDER BY id');
        return View::render($ctx, 'Admins', 'admins', ['admins' => $admins, 'roles' => Ctx::ROLE_LABELS, 'temp' => ['email' => $email, 'password' => $temp]]);
    }

    public function update(Ctx $ctx): string
    {
        $a = Database::one('SELECT * FROM admin_users WHERE id = ?', [$ctx->id()]);
        if (!$a) {
            throw new HttpError(404, 'Admin not found.');
        }
        $op = $ctx->post('op');
        $self = (int)$a['id'] === (int)$ctx->admin['id'];
        if ($self && in_array($op, ['role', 'disable', 'reset_2fa', 'reset_password'], true)) {
            throw new Redirect('/admin/admins', 'You cannot change your own access here. Use My account, or ask another owner.', 'error');
        }
        $lastOwner = $a['role'] === 'owner' && $a['status'] === 'active'
            && (int)Database::value("SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND status = 'active'") <= 1;
        switch ($op) {
            case 'role':
                $role = $ctx->post('role');
                if (!isset(Ctx::ROLES[$role]) || ($lastOwner && $role !== 'owner')) {
                    throw new Redirect('/admin/admins', 'Invalid role, or this is the last owner.', 'error');
                }
                Database::run('UPDATE admin_users SET role = ? WHERE id = ?', [$role, $a['id']]);
                Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$a['id']]);
                Audit::log($ctx, 'admin_role_changed', 'admin', (int)$a['id'], ['from' => $a['role'], 'to' => $role]);
                throw new Redirect('/admin/admins', "{$a['email']} is now $role.");
            case 'disable':
            case 'enable':
                if ($op === 'disable' && $lastOwner) {
                    throw new Redirect('/admin/admins', 'You cannot disable the last owner.', 'error');
                }
                Database::run('UPDATE admin_users SET status = ?, failed_logins = 0, locked_until = NULL WHERE id = ?', [$op === 'disable' ? 'disabled' : 'active', $a['id']]);
                Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$a['id']]);
                Audit::log($ctx, 'admin_' . $op . 'd', 'admin', (int)$a['id']);
                throw new Redirect('/admin/admins', "{$a['email']} " . ($op === 'disable' ? 'disabled and signed out.' : 'enabled.'));
            case 'reset_2fa':
                Database::run('UPDATE admin_users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?', [$a['id']]);
                Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$a['id']]);
                Audit::log($ctx, '2fa_reset', 'admin', (int)$a['id']);
                throw new Redirect('/admin/admins', "Two-step sign-in removed for {$a['email']}. They must set it up again.");
            case 'reset_password':
                $temp = self::tempPassword();
                Database::run('UPDATE admin_users SET password_hash = ?, must_change_password = 1, failed_logins = 0, locked_until = NULL WHERE id = ?', [Auth::hashPassword($temp), $a['id']]);
                Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$a['id']]);
                Audit::log($ctx, 'password_reset', 'admin', (int)$a['id']);
                $admins = Database::all('SELECT id, email, name, role, status, totp_enabled_at, last_login_at, locked_until, created_at FROM admin_users ORDER BY id');
                return View::render($ctx, 'Admins', 'admins', ['admins' => $admins, 'roles' => Ctx::ROLE_LABELS, 'temp' => ['email' => $a['email'], 'password' => $temp]]);
        }
        throw new Redirect('/admin/admins', 'Unknown action.', 'error');
    }

    public static function tempPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Kq'), '=') . '7a';
    }
}
