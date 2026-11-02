<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

/** Admin cookie sessions: opaque token in an HttpOnly cookie, SHA-256 stored, idle and absolute expiry. */
final class Session
{
    public static function start(Request $req, int $adminId, string $stage): void
    {
        self::end($req);
        $token = Auth::newToken();
        $minutes = $stage === '2fa' ? 5 : Kernel::MAX_HOURS * 60;
        Database::run(
            'INSERT INTO admin_sessions (admin_id, token_hash, csrf_token, stage, ip, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
            [$adminId, Auth::hashToken($token), bin2hex(random_bytes(32)), $stage, $req->ip(), mb_substr((string)$req->header('user-agent'), 0, 255), $minutes]
        );
        self::cookie(Kernel::COOKIE, $token, 0);
    }

    /** Session in any stage, or null. Applies idle timeout to active sessions. */
    public static function current(Request $req): ?array
    {
        $token = $_COOKIE[Kernel::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $token)) {
            return null;
        }
        $s = Database::one(
            'SELECT s.*, a.email, a.name, a.role, a.status AS admin_status, a.totp_secret, a.totp_enabled_at, a.totp_last_step,
                    a.must_change_password
               FROM admin_sessions s JOIN admin_users a ON a.id = s.admin_id
              WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP()
                AND (s.stage = \'2fa\' OR s.last_seen_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [Auth::hashToken($token), Kernel::IDLE_MINUTES]
        );
        if ($s === null || $s['admin_status'] !== 'active') {
            return null;
        }
        return $s;
    }

    public static function require(Request $req): Ctx
    {
        $s = self::current($req);
        if ($s === null || $s['stage'] !== 'active') {
            throw new Redirect('/admin/login');
        }
        Database::run('UPDATE admin_sessions SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$s['id']]);
        $admin = [
            'id' => (int)$s['admin_id'], 'email' => $s['email'], 'name' => $s['name'], 'role' => $s['role'],
            'totp_enabled_at' => $s['totp_enabled_at'], 'must_change_password' => (int)$s['must_change_password'],
        ];
        return new Ctx($req, $admin, $s);
    }

    public static function end(Request $req): void
    {
        $token = $_COOKIE[Kernel::COOKIE] ?? '';
        if (is_string($token) && $token !== '') {
            Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ? AND revoked_at IS NULL', [Auth::hashToken($token)]);
        }
        self::cookie(Kernel::COOKIE, '', time() - 3600);
    }

    public static function flash(Ctx $ctx, string $message, string $kind): void
    {
        Database::run('UPDATE admin_sessions SET flash = ? WHERE id = ?', [json_encode(['kind' => $kind, 'msg' => mb_substr($message, 0, 400)]), $ctx->session['id']]);
    }

    /** Reads and clears the flash message. */
    public static function takeFlash(Ctx $ctx): ?array
    {
        if (!$ctx->session || empty($ctx->session['flash'])) {
            return null;
        }
        Database::run('UPDATE admin_sessions SET flash = NULL WHERE id = ?', [$ctx->session['id']]);
        $f = json_decode($ctx->session['flash'], true);
        $ctx->session['flash'] = null;
        return is_array($f) ? $f : null;
    }

    public static function cookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => (Kernel::$base ?: '') . '/admin',
            'secure' => Kernel::secureCookies(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
