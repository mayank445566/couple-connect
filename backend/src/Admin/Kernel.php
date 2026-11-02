<?php
declare(strict_types=1);

namespace App\Admin;

use App\Admin\Pages\AccountPage;
use App\Admin\Pages\AdminsPage;
use App\Admin\Pages\AuditPage;
use App\Admin\Pages\AuthPage;
use App\Admin\Pages\CasesPage;
use App\Admin\Pages\ContentPage;
use App\Admin\Pages\DashboardPage;
use App\Admin\Pages\PaymentsPage;
use App\Admin\Pages\ReportsPage;
use App\Admin\Pages\SettingsPage;
use App\Admin\Pages\UsersPage;
use App\Core\Config;
use App\Core\Request;

/**
 * Server-rendered admin panel at /admin. Separate accounts, cookie sessions
 * (HttpOnly, SameSite=Strict), CSRF token on every POST, role-based access,
 * and an audit log entry for every change.
 */
final class Kernel
{
    public const COOKIE = 'cc_admin';
    public const PRE_COOKIE = 'cc_admin_pre';
    public const IDLE_MINUTES = 30;
    public const MAX_HOURS = 12;

    public static string $base = '';
    public static string $nonce = '';

    /** [method, path pattern, handler, permission|null (null = any signed-in admin), public?] */
    private static function routes(): array
    {
        return [
            ['GET', '/admin/login', [AuthPage::class, 'loginForm'], null, true],
            ['POST', '/admin/login', [AuthPage::class, 'login'], null, true],
            ['GET', '/admin/2fa', [AuthPage::class, 'twoFactorForm'], null, true],
            ['POST', '/admin/2fa', [AuthPage::class, 'twoFactor'], null, true],
            ['POST', '/admin/logout', [AuthPage::class, 'logout'], null, false],

            ['GET', '/admin', [DashboardPage::class, 'index'], null, false],

            ['GET', '/admin/account', [AccountPage::class, 'show'], null, false],
            ['POST', '/admin/account/password', [AccountPage::class, 'password'], null, false],
            ['POST', '/admin/account/2fa/start', [AccountPage::class, 'start2fa'], null, false],
            ['POST', '/admin/account/2fa/confirm', [AccountPage::class, 'confirm2fa'], null, false],
            ['POST', '/admin/account/2fa/disable', [AccountPage::class, 'disable2fa'], null, false],
            ['POST', '/admin/account/sessions/{id}/revoke', [AccountPage::class, 'revokeSession'], null, false],

            ['GET', '/admin/users', [UsersPage::class, 'index'], 'users.view', false],
            ['GET', '/admin/users/{id}', [UsersPage::class, 'show'], 'users.view', false],
            ['POST', '/admin/users/{id}/status', [UsersPage::class, 'status'], 'users.moderate', false],
            ['POST', '/admin/users/{id}/sessions/revoke', [UsersPage::class, 'revokeSessions'], 'users.moderate', false],
            ['POST', '/admin/users/{id}/entitlements', [UsersPage::class, 'entitlement'], 'entitlements', false],

            ['GET', '/admin/reports', [ReportsPage::class, 'index'], 'reports', false],
            ['GET', '/admin/reports/{id}', [ReportsPage::class, 'show'], 'reports', false],
            ['GET', '/admin/reports/{id}/media/{mediaId}', [ReportsPage::class, 'media'], 'reports', false],
            ['POST', '/admin/reports/{id}', [ReportsPage::class, 'update'], 'reports', false],

            ['GET', '/admin/orders', [PaymentsPage::class, 'index'], 'payments', false],
            ['GET', '/admin/orders/{id}', [PaymentsPage::class, 'show'], 'payments', false],
            ['POST', '/admin/orders/{id}/refund', [PaymentsPage::class, 'refund'], 'payments', false],
            ['GET', '/admin/webhooks', [PaymentsPage::class, 'webhooks'], 'payments', false],

            ['GET', '/admin/games', [ContentPage::class, 'games'], 'content', false],
            ['GET', '/admin/games/{id}', [ContentPage::class, 'game'], 'content', false],
            ['POST', '/admin/games/{id}', [ContentPage::class, 'saveGame'], 'content', false],
            ['POST', '/admin/games/{id}/packs', [ContentPage::class, 'createPack'], 'content', false],
            ['GET', '/admin/packs/{id}', [ContentPage::class, 'pack'], 'content', false],
            ['POST', '/admin/packs/{id}', [ContentPage::class, 'savePack'], 'content', false],
            ['POST', '/admin/packs/{id}/questions', [ContentPage::class, 'addQuestion'], 'content', false],
            ['GET', '/admin/questions/{id}', [ContentPage::class, 'question'], 'content', false],
            ['POST', '/admin/questions/{id}', [ContentPage::class, 'saveQuestion'], 'content', false],
            ['GET', '/admin/themes', [ContentPage::class, 'themes'], 'content', false],
            ['GET', '/admin/themes/new', [ContentPage::class, 'theme'], 'content', false],
            ['GET', '/admin/themes/{id}', [ContentPage::class, 'theme'], 'content', false],
            ['POST', '/admin/themes/new', [ContentPage::class, 'saveTheme'], 'content', false],
            ['POST', '/admin/themes/{id}', [ContentPage::class, 'saveTheme'], 'content', false],

            ['GET', '/admin/products', [SettingsPage::class, 'products'], 'products', false],
            ['POST', '/admin/products/{id}', [SettingsPage::class, 'saveProduct'], 'products', false],
            ['GET', '/admin/settings', [SettingsPage::class, 'show'], 'settings', false],
            ['POST', '/admin/settings', [SettingsPage::class, 'save'], 'settings', false],

            ['GET', '/admin/admins', [AdminsPage::class, 'index'], 'admins', false],
            ['POST', '/admin/admins', [AdminsPage::class, 'create'], 'admins', false],
            ['POST', '/admin/admins/{id}', [AdminsPage::class, 'update'], 'admins', false],

            ['GET', '/admin/cases', [CasesPage::class, 'index'], 'case_access', false],
            ['POST', '/admin/cases', [CasesPage::class, 'open'], 'case_access', false],
            ['GET', '/admin/cases/{id}', [CasesPage::class, 'show'], 'case_access', false],
            ['GET', '/admin/cases/{id}/media/{mediaId}', [CasesPage::class, 'media'], 'case_access', false],
            ['POST', '/admin/cases/{id}/close', [CasesPage::class, 'close'], 'case_access', false],

            ['GET', '/admin/audit', [AuditPage::class, 'index'], 'audit', false],
        ];
    }

    public static function handle(Request $req): never
    {
        self::$base = self::detectBase();
        self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        header_remove('Access-Control-Allow-Origin');
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'none'; style-src 'nonce-" . self::$nonce . "'; img-src 'self' data:; media-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        try {
            [$handler, $perm, $public, $params] = self::match($req);
            $req->params = $params;
            $ctx = new Ctx($req, null, null);
            if ($req->method === 'POST') {
                self::checkOrigin();
            }
            if (!$public) {
                $ctx = Session::require($req);
                if ($req->method === 'POST' && !hash_equals($ctx->session['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
                    throw new HttpError(419, 'Your form expired. Go back, reload the page and try again.');
                }
                self::gate($ctx, $handler);
                if ($perm !== null && !$ctx->can($perm)) {
                    throw new HttpError(403, 'Your role does not have access to this page.');
                }
            }
            [$class, $fn] = $handler;
            $out = (new $class())->$fn($ctx);
            header('Content-Type: text/html; charset=utf-8');
            echo $out;
        } catch (Redirect $r) {
            if ($r->flash !== null && isset($ctx) && $ctx->session) {
                Session::flash($ctx, $r->flash, $r->kind);
            }
            header('Location: ' . self::url($r->path), true, 303);
        } catch (HttpError $e) {
            http_response_code($e->status);
            header('Content-Type: text/html; charset=utf-8');
            echo View::error($e->status, $e->getMessage(), $ctx ?? null);
        } catch (\Throwable $e) {
            $ref = bin2hex(random_bytes(4));
            error_log("[admin $ref] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            $msg = Config::get('APP_ENV') === 'local' ? $e->getMessage() : "Something went wrong (ref $ref).";
            echo View::error(500, $msg, null);
        }
        exit;
    }

    /** First sign-in steps that must be finished before the rest of the panel opens. */
    private static function gate(Ctx $ctx, array $handler): void
    {
        $allowed = [[AuthPage::class, 'logout'], [AccountPage::class, 'show'], [AccountPage::class, 'password'],
                    [AccountPage::class, 'start2fa'], [AccountPage::class, 'confirm2fa']];
        $needsPassword = (int)$ctx->admin['must_change_password'] === 1;
        $needs2fa = Config::bool('ADMIN_REQUIRE_2FA', true) && $ctx->admin['totp_enabled_at'] === null;
        if (($needsPassword || $needs2fa) && !in_array($handler, $allowed, true)) {
            throw new Redirect('/admin/account', $needsPassword ? 'Set a new password to continue.' : 'Turn on two-step sign-in to continue.', 'error');
        }
    }

    private static function match(Request $req): array
    {
        $allowed = false;
        foreach (self::routes() as [$method, $pattern, $handler, $perm, $public]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            if ($method !== $req->method) {
                $allowed = true;
                continue;
            }
            return [$handler, $perm, $public, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
        }
        throw new HttpError($allowed ? 405 : 404, $allowed ? 'Method not allowed.' : 'Page not found.');
    }

    /** Browsers send Origin on POST; reject cross-site posts even before the CSRF check. */
    private static function checkOrigin(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($origin === null || $origin === 'null') {
            return;
        }
        $host = parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
        if (!hash_equals((string)($_SERVER['HTTP_HOST'] ?? ''), $host)) {
            throw new HttpError(403, 'Cross-site request blocked.');
        }
    }

    private static function detectBase(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($script !== '' && str_starts_with($uri, $script)) {
            return $script;
        }
        if ($dir !== '' && str_starts_with($uri, $dir)) {
            return $dir;
        }
        return '';
    }

    public static function url(string $path, array $query = []): string
    {
        $q = array_filter($query, fn($v) => $v !== null && $v !== '');
        return self::$base . $path . ($q ? '?' . http_build_query($q) : '');
    }

    public static function secureCookies(): bool
    {
        return Config::get('APP_ENV') === 'production' || ($_SERVER['HTTPS'] ?? '') === 'on';
    }
}
