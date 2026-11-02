<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\Redirect;
use App\Admin\Totp;
use App\Admin\View;
use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Validator;

final class AccountPage
{
    public function show(Ctx $ctx): string
    {
        $a = Database::one('SELECT * FROM admin_users WHERE id = ?', [$ctx->admin['id']]);
        $pending = $a['totp_secret'] !== null && $a['totp_enabled_at'] === null ? Totp::open($a['totp_secret']) : null;
        $sessions = Database::all(
            'SELECT id, ip, user_agent, created_at, last_seen_at FROM admin_sessions
              WHERE admin_id = ? AND revoked_at IS NULL AND stage = \'active\' AND expires_at > UTC_TIMESTAMP()
                AND last_seen_at > UTC_TIMESTAMP() - INTERVAL 30 MINUTE ORDER BY last_seen_at DESC',
            [$ctx->admin['id']]
        );
        return View::render($ctx, 'My account', 'account', [
            'a' => $a,
            'pending' => $pending,
            'uri' => $pending ? Totp::uri($pending, $a['email']) : null,
            'sessions' => $sessions,
            'require2fa' => Config::bool('ADMIN_REQUIRE_2FA', true),
        ]);
    }

    public function password(Ctx $ctx): string
    {
        $a = Database::one('SELECT password_hash FROM admin_users WHERE id = ?', [$ctx->admin['id']]);
        if (!password_verify((string)($_POST['current'] ?? ''), $a['password_hash'])) {
            throw new Redirect('/admin/account', 'Current password is incorrect.', 'error');
        }
        $new = (string)($_POST['new'] ?? '');
        if (mb_strlen($new) < 12) {
            throw new Redirect('/admin/account', 'Admin passwords need at least 12 characters.', 'error');
        }
        try {
            Validator::check(['p' => $new], ['p' => 'required,password']);
        } catch (ApiException $e) {
            throw new Redirect('/admin/account', $e->fields['p'] ?? 'Choose a stronger password.', 'error');
        }
        if ($new !== (string)($_POST['confirm'] ?? '')) {
            throw new Redirect('/admin/account', 'The two new passwords do not match.', 'error');
        }
        Database::run('UPDATE admin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [Auth::hashPassword($new), $ctx->admin['id']]);
        // Sign out every other session of this admin
        Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND id <> ? AND revoked_at IS NULL', [$ctx->admin['id'], $ctx->session['id']]);
        Audit::log($ctx, 'password_changed', 'admin', $ctx->admin['id']);
        throw new Redirect('/admin/account', 'Password changed. Your other sessions were signed out.');
    }

    public function start2fa(Ctx $ctx): string
    {
        Database::run('UPDATE admin_users SET totp_secret = ?, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ? AND totp_enabled_at IS NULL',
            [Totp::seal(Totp::newSecret()), $ctx->admin['id']]);
        throw new Redirect('/admin/account');
    }

    public function confirm2fa(Ctx $ctx): string
    {
        $a = Database::one('SELECT totp_secret, totp_enabled_at FROM admin_users WHERE id = ?', [$ctx->admin['id']]);
        if ($a['totp_secret'] === null || $a['totp_enabled_at'] !== null) {
            throw new Redirect('/admin/account');
        }
        $step = Totp::verify(Totp::open($a['totp_secret']), $ctx->post('code'));
        if ($step === null) {
            throw new Redirect('/admin/account', 'That code is not valid. Check the key you entered and the time on your phone.', 'error');
        }
        Database::run('UPDATE admin_users SET totp_enabled_at = UTC_TIMESTAMP(), totp_last_step = ? WHERE id = ?', [$step, $ctx->admin['id']]);
        Audit::log($ctx, '2fa_enabled', 'admin', $ctx->admin['id']);
        throw new Redirect('/admin', 'Two-step sign-in is on.');
    }

    public function disable2fa(Ctx $ctx): string
    {
        if (Config::bool('ADMIN_REQUIRE_2FA', true)) {
            throw new Redirect('/admin/account', 'Two-step sign-in is required for all admins on this server.', 'error');
        }
        $a = Database::one('SELECT password_hash FROM admin_users WHERE id = ?', [$ctx->admin['id']]);
        if (!password_verify((string)($_POST['current'] ?? ''), $a['password_hash'])) {
            throw new Redirect('/admin/account', 'Current password is incorrect.', 'error');
        }
        Database::run('UPDATE admin_users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?', [$ctx->admin['id']]);
        Audit::log($ctx, '2fa_disabled', 'admin', $ctx->admin['id']);
        throw new Redirect('/admin/account', 'Two-step sign-in is off.');
    }

    public function revokeSession(Ctx $ctx): string
    {
        Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND admin_id = ? AND id <> ?', [$ctx->id(), $ctx->admin['id'], $ctx->session['id']]);
        throw new Redirect('/admin/account', 'Session signed out.');
    }
}
