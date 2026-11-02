<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Kernel;
use App\Admin\Redirect;
use App\Admin\Session;
use App\Admin\Totp;
use App\Admin\View;
use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;

final class AuthPage
{
    private const MAX_FAILS = 10;
    private const LOCK_MINUTES = 15;

    public function loginForm(Ctx $ctx, string $error = '', string $email = ''): string
    {
        $s = Session::current($ctx->req);
        if ($s && $s['stage'] === 'active') {
            throw new Redirect('/admin');
        }
        return View::layout(null, 'Sign in', View::partial(null, 'login', ['error' => $error, 'email' => $email, 'pre' => $this->preToken()]));
    }

    public function login(Ctx $ctx): string
    {
        $this->checkPre();
        $email = strtolower($ctx->post('email'));
        $password = (string)($_POST['password'] ?? '');
        try {
            RateLimiter::hit('admin-login:ip:' . $ctx->req->ip(), 20, 900);
            RateLimiter::hit('admin-login:email:' . $email, 10, 900);
        } catch (ApiException $e) {
            http_response_code(429);
            return $this->loginForm($ctx, 'Too many attempts. Wait 15 minutes and try again.', $email);
        }
        $a = Database::one('SELECT * FROM admin_users WHERE email = ?', [$email]);
        $ok = password_verify($password, $a['password_hash'] ?? Auth::DUMMY_HASH);
        $locked = $a && $a['locked_until'] !== null && strtotime($a['locked_until'] . ' UTC') > time();
        if (!$a || !$ok || $locked || $a['status'] !== 'active') {
            if ($a && !$ok && !$locked) {
                Database::run(
                    'UPDATE admin_users SET failed_logins = failed_logins + 1,
                            locked_until = IF(failed_logins + 1 >= ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE, locked_until) WHERE id = ?',
                    [self::MAX_FAILS, self::LOCK_MINUTES, $a['id']]
                );
            }
            Audit::log($ctx, 'login_failed', 'admin', $a ? (int)$a['id'] : null, ['email' => mb_substr($email, 0, 190), 'reason' => $locked ? 'locked' : (!$a ? 'unknown' : (!$ok ? 'password' : 'disabled'))]);
            http_response_code(401);
            return $this->loginForm($ctx, $locked ? 'This account is temporarily locked after too many failed attempts. Try again in 15 minutes.' : 'Email or password is incorrect.', $email);
        }
        Database::run('UPDATE admin_users SET failed_logins = 0, locked_until = NULL WHERE id = ?', [$a['id']]);
        if (password_needs_rehash($a['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Database::run('UPDATE admin_users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($password), $a['id']]);
        }
        if ($a['totp_enabled_at'] !== null) {
            Session::start($ctx->req, (int)$a['id'], '2fa');
            throw new Redirect('/admin/2fa');
        }
        $this->finish($ctx, $a);
    }

    public function twoFactorForm(Ctx $ctx, string $error = ''): string
    {
        $s = Session::current($ctx->req);
        if (!$s || $s['stage'] !== '2fa') {
            throw new Redirect('/admin/login');
        }
        return View::layout(null, 'Two-step sign in', View::partial(null, 'twofa', ['error' => $error, 'pre' => $this->preToken()]));
    }

    public function twoFactor(Ctx $ctx): string
    {
        $this->checkPre();
        $s = Session::current($ctx->req);
        if (!$s || $s['stage'] !== '2fa') {
            throw new Redirect('/admin/login');
        }
        $step = Totp::verify(Totp::open((string)$s['totp_secret']), $ctx->post('code'), $s['totp_last_step'] !== null ? (int)$s['totp_last_step'] : null);
        if ($step === null) {
            Database::run('UPDATE admin_sessions SET twofa_attempts = twofa_attempts + 1 WHERE id = ?', [$s['id']]);
            if ((int)$s['twofa_attempts'] + 1 >= 5) {
                Session::end($ctx->req);
                Audit::log($ctx, 'login_2fa_failed', 'admin', (int)$s['admin_id'], ['email' => $s['email']]);
                throw new Redirect('/admin/login');
            }
            http_response_code(401);
            return $this->twoFactorForm($ctx, 'That code is not valid. Check the time on your phone and try again.');
        }
        Database::run('UPDATE admin_users SET totp_last_step = ? WHERE id = ?', [$step, $s['admin_id']]);
        $this->finish($ctx, Database::one('SELECT * FROM admin_users WHERE id = ?', [$s['admin_id']]));
    }

    public function logout(Ctx $ctx): string
    {
        Audit::log($ctx, 'logout', 'admin', $ctx->admin['id']);
        Session::end($ctx->req);
        throw new Redirect('/admin/login');
    }

    private function finish(Ctx $ctx, array $a): never
    {
        Session::start($ctx->req, (int)$a['id'], 'active');
        Database::run('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$a['id']]);
        Audit::log(new Ctx($ctx->req, ['id' => (int)$a['id'], 'email' => $a['email']], null), 'login', 'admin', (int)$a['id']);
        throw new Redirect('/admin');
    }

    /** Double-submit token for the sign-in forms, which run before a session exists. */
    private function preToken(): string
    {
        $t = $_COOKIE[Kernel::PRE_COOKIE] ?? '';
        if (!is_string($t) || !preg_match('/^[a-f0-9]{64}$/', $t)) {
            $t = bin2hex(random_bytes(32));
            Session::cookie(Kernel::PRE_COOKIE, $t, 0);
        }
        return $t;
    }

    private function checkPre(): void
    {
        $c = $_COOKIE[Kernel::PRE_COOKIE] ?? '';
        if (!is_string($c) || $c === '' || !hash_equals($c, (string)($_POST['_pre'] ?? ''))) {
            throw new HttpError(419, 'Your form expired. Reload the sign-in page and try again.');
        }
    }
}
