<?php
declare(strict_types=1);
/**
 * One-time web installer for cPanel hosts without Terminal access.
 * Writes ../.env with fresh keys, creates the tables, loads starter content and the first
 * owner admin, then deletes itself. It refuses to run once .env exists.
 *
 * Only a localhost database is accepted, so nobody who finds this page before you can
 * point your server at a database they control: they would need your cPanel database password.
 */

define('BASE_PATH', dirname(__DIR__));
$ENV = BASE_PATH . '/.env';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Robots-Tag: noindex');

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function page(string $title, string $body): never
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($title) . ' · Couple Connect setup</title><style>'
        . ':root{--bg:#f6f5f7;--card:#fff;--text:#231a20;--muted:#6d6168;--line:#e6e0e4;--accent:#c2375c;--ok:#1d7a46;--err:#b3261e}'
        . '@media (prefers-color-scheme:dark){:root{--bg:#141114;--card:#1e1a1e;--text:#f1ecef;--muted:#a99da4;--line:#342d33;--accent:#ff6b8f;--ok:#6fd39b;--err:#ff8a80}}'
        . '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,sans-serif}main{max-width:720px;margin:0 auto;padding:24px 16px 60px}'
        . '.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin-bottom:16px}h1{font-size:22px}h2{font-size:17px;margin:0 0 8px}'
        . 'label{display:block;margin:10px 0 4px;font-weight:600;font-size:14px}input,select{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);font:inherit}'
        . 'button{padding:10px 18px;border-radius:8px;border:0;background:var(--accent);color:#fff;font:inherit;cursor:pointer}.muted{color:var(--muted);font-size:13px}'
        . '.ok{color:var(--ok)}.bad{color:var(--err)}.err{background:var(--card);border:1px solid var(--err);color:var(--err);padding:10px 14px;border-radius:8px;margin-bottom:16px}'
        . 'code,pre{font-family:ui-monospace,monospace;font-size:13px}pre{white-space:pre-wrap;word-break:break-all;background:var(--bg);padding:10px;border-radius:8px}'
        . 'table{width:100%;border-collapse:collapse}td{padding:4px 0;border-bottom:1px solid var(--line)}'
        . '</style></head><body><main><h1>Couple Connect setup</h1>' . $body . '</main></body></html>';
    exit;
}

if (is_file($ENV)) {
    http_response_code(403);
    page('Already installed', '<div class="card"><h2>Already installed</h2><p>This server already has a <code>.env</code> file, so the installer is switched off. Delete <code>public/install.php</code> if it is still there.</p></div>');
}

// ---- Requirements -------------------------------------------------------------------
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$home = dirname(BASE_PATH, 2);
$defaultStorage = $home . '/couple-connect-storage';
$checks = [
    ['PHP 8.2 or newer', PHP_VERSION_ID >= 80200, 'You have ' . PHP_VERSION . '. Change it in cPanel → MultiPHP Manager.'],
];
foreach (['pdo_mysql', 'sodium', 'fileinfo', 'mbstring', 'gd', 'curl', 'openssl', 'json'] as $ext) {
    if ($ext === 'sodium' && !extension_loaded('sodium') && is_file(BASE_PATH . '/vendor/paragonie/sodium_compat/autoload.php')) {
        // Not blocking: the bundled pure-PHP library takes over, but photo and video encryption is much slower
        $checks[] = ['PHP extension sodium', true, '', 'Using the slower built-in fallback. Photos and videos will upload and open slowly. Ask your host to enable sodium for PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ' (php.ini: ' . (php_ini_loaded_file() ?: 'none') . ').'];
        continue;
    }
    $checks[] = ["PHP extension $ext", extension_loaded($ext), 'Turn it on in cPanel → Select PHP Version → Extensions.'];
}
$checks[] = ['Composer packages (vendor/) uploaded', is_file(BASE_PATH . '/vendor/autoload.php'), 'Upload the full package, including the backend/vendor folder.'];
$checks[] = ['Backend folder writable (to save .env)', is_writable(BASE_PATH), 'Give the backend folder permission 755 in File Manager.'];
$checks[] = ['Migrations found', (bool)glob(dirname(BASE_PATH) . '/database/migrations/*.sql') || (bool)glob(BASE_PATH . '/database/migrations/*.sql'), 'Upload the database folder next to backend.'];
$docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
$checks[] = [
    'Document root is backend/public',
    $docRoot === '' || $docRoot === realpath(__DIR__),
    'The web address must point at the public folder itself, or anyone can download .env. Move the couple-connect folder to your home folder (/home/USER), then in cPanel → Domains set this domain\'s Document Root to couple-connect/backend/public.',
];
$checks[] = ['HTTPS', $https, 'Turn on AutoSSL (cPanel → SSL/TLS Status) and open this page with https://. Required in production.'];
$blocking = array_filter($checks, fn($c) => !$c[1] && $c[0] !== 'HTTPS');

// ---- CSRF: double-submit cookie ----------------------------------------------------
$csrf = $_COOKIE['cc_install'] ?? '';
if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/', $csrf)) {
    $csrf = bin2hex(random_bytes(32));
    setcookie('cc_install', $csrf, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => $https]);
}

$v = fn(string $k, string $d = '') => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $d;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) {
        $errors[] = 'The form expired. Reload the page and try again.';
    }
    if ($blocking) {
        $errors[] = 'Fix the requirements marked in red first.';
    }
    $appUrl = rtrim($v('app_url'), '/');
    $dbHost = $v('db_host', 'localhost');
    $dbName = $v('db_name');
    $dbUser = $v('db_user');
    $dbPass = is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '';
    $storage = rtrim($v('storage', $defaultStorage), '/');
    $email = strtolower($v('admin_email'));
    $name = $v('admin_name');
    $pass = is_string($_POST['admin_pass'] ?? null) ? $_POST['admin_pass'] : '';
    $env = $v('app_env', 'production') === 'local' ? 'local' : 'production';
    if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $appUrl)) {
        $errors[] = 'API address must be a full URL, like https://api.yourdomain.com.';
    }
    if ($env === 'production' && !str_starts_with($appUrl, 'https://')) {
        $errors[] = 'In production the API address must start with https://.';
    }
    if (!in_array($dbHost, ['localhost', '127.0.0.1'], true)) {
        $errors[] = 'Only a database on this server (localhost) can be used from the installer. For anything else, edit .env by hand.';
    }
    if ($dbName === '' || $dbUser === '') {
        $errors[] = 'Enter the database name and user from cPanel → MySQL Databases.';
    }
    if (!str_starts_with($storage, '/') || str_contains($storage, '..')) {
        $errors[] = 'Storage folder must be an absolute path.';
    } elseif (str_starts_with(realpath(__DIR__) ?: __DIR__, $storage) || str_starts_with($storage, realpath(__DIR__) ?: __DIR__) || str_contains($storage, '/public_html')) {
        $errors[] = 'Storage folder must be outside every web-visible folder (not inside public or public_html).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid admin email.';
    }
    if ($name === '' || mb_strlen($name) > 60) {
        $errors[] = 'Enter your name (up to 60 characters).';
    }
    if (mb_strlen($pass) < 12 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors[] = 'Admin password needs 12+ characters with a letter and a number.';
    }
    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;port=3306;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');
            if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn() > 0) {
                $errors[] = 'This database already has Couple Connect tables. Use an empty database, or write .env by hand to reuse it.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Could not connect to the database: check the name, user, password, and that the user is added to the database with ALL PRIVILEGES.';
        }
    }
    if (!$errors && !is_dir($storage) && !@mkdir($storage, 0750, true)) {
        $errors[] = "Could not create $storage. Create it in File Manager and try again.";
    }
    if (!$errors && !is_writable($storage)) {
        $errors[] = "$storage is not writable.";
    }

    if (!$errors) {
        $values = [
            'APP_ENV' => $env,
            'APP_URL' => $appUrl,
            'APP_KEY' => bin2hex(random_bytes(32)),
            'MEDIA_KEY' => bin2hex(random_bytes(32)),
            'DB_HOST' => $dbHost,
            'DB_PORT' => '3306',
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
            'STORAGE_PATH' => $storage,
            'MAIL_DRIVER' => 'log',
        ];
        $tmp = BASE_PATH . '/.env.installing';
        $template = (string)@file_get_contents(BASE_PATH . '/.env.example');
        foreach ($values as $k => $val) {
            $line = $k . '="' . $val . '"';
            if (str_contains($val, '"')) {
                $line = $k . "='" . $val . "'";
            }
            $template = preg_match('/^' . $k . '=.*$/m', $template)
                ? preg_replace('/^' . $k . '=.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $template, 1)
                : $template . "\n" . $line . "\n";
        }
        if (str_contains($dbPass, '"') && str_contains($dbPass, "'")) {
            $errors[] = 'The database password contains both kinds of quote marks. Change it in cPanel and try again.';
        } elseif (@file_put_contents($tmp, $template, LOCK_EX) === false) {
            $errors[] = 'Could not write the settings file. Make the backend folder writable (755).';
        } else {
            @chmod($tmp, 0600);
            try {
                require BASE_PATH . '/vendor/autoload.php';
                \App\Core\Config::load($tmp);
                date_default_timezone_set('UTC');
                \App\Setup\Installer::migrate();
                $counts = \App\Setup\Installer::seed();
                \App\Core\Database::run(
                    'INSERT INTO admin_users (email, name, password_hash, role, must_change_password) VALUES (?, ?, ?, \'owner\', 0)',
                    [$email, mb_substr($name, 0, 60), \App\Core\Auth::hashPassword($pass)]
                );
                \App\Core\Database::run("INSERT INTO admin_audit_log (admin_email, action, target_type, ip) VALUES (?, 'installed', 'settings', ?)", [$email, $_SERVER['REMOTE_ADDR'] ?? '']);
                if (!@rename($tmp, $ENV)) {
                    throw new RuntimeException('Could not rename .env.installing to .env');
                }
            } catch (Throwable $e) {
                @unlink($tmp);
                error_log('[install] ' . $e->getMessage());
                $errors[] = 'Setup stopped: ' . $e->getMessage() . '. Nothing was saved to .env; empty the database in phpMyAdmin and try again.';
            }
        }
        if (!$errors) {
            $deleted = @unlink(__FILE__);
            setcookie('cc_install', '', ['expires' => time() - 3600, 'path' => '/']);
            $php = PHP_BINDIR . '/php';
            $cron = '* * * * * ' . $php . ' ' . BASE_PATH . '/bin/cron.php >/dev/null 2>&1';
            page('Done', '<div class="card"><h2 class="ok">Installed</h2>'
                . '<p>Created the tables and loaded ' . (int)$counts['g'] . ' games, ' . (int)$counts['q'] . ' questions and ' . (int)$counts['t'] . ' themes.</p>'
                . ($deleted ? '<p class="ok">This installer has deleted itself.</p>' : '<p class="bad"><b>Delete <code>public/install.php</code> now</b> in File Manager. (It is already switched off, but should not stay on the server.)</p>')
                . '</div><div class="card"><h2>Next steps</h2><ol>'
                . '<li>cPanel → <b>Cron Jobs</b> → add, every minute:<pre>' . h($cron) . '</pre></li>'
                . '<li>Sign in to the admin panel: <a href="' . h($appUrl) . '/admin">' . h($appUrl) . '/admin</a>, then turn on two-step sign-in.</li>'
                . '<li>Edit <code>' . h(BASE_PATH) . '/.env</code> in File Manager to add SMTP email (<code>MAIL_DRIVER=smtp</code>), Razorpay keys, and Firebase push. The admin <b>Settings</b> page shows what is still missing.</li>'
                . '<li>Back up <code>.env</code> somewhere safe. If <code>MEDIA_KEY</code> is lost, photos cannot be decrypted.</li>'
                . '<li>Open <a href="' . h($appUrl) . '/health">' . h($appUrl) . '/health</a>: it should say <code>"status":"ok"</code>.</li></ol></div>');
        }
    }
}

// ---- Form ------------------------------------------------------------------------------
$guessUrl = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'api.yourdomain.com') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$rows = '';
foreach ($checks as $c) {
    [$label, $ok, $fix] = $c;
    $warn = $c[3] ?? '';
    $rows .= '<tr><td>' . h($label) . '</td><td class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? ($warn ? 'Fallback' : 'OK') : 'Missing') . '</td></tr>'
        . ($ok ? ($warn ? '<tr><td colspan="2" class="muted">' . h($warn) . '</td></tr>' : '') : '<tr><td colspan="2" class="muted">' . h($fix) . '</td></tr>');
}
$errHtml = $errors ? '<div class="err" role="alert">' . implode('<br>', array_map('h', $errors)) . '</div>' : '';
page('Install', $errHtml
    . '<div class="card"><h2>1. Server check</h2><table>' . $rows . '</table></div>'
    . '<form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="' . h($csrf) . '">'
    . '<div class="card"><h2>2. API address</h2><label for="app_url">Address of this API</label><input id="app_url" name="app_url" required value="' . h($v('app_url', $guessUrl)) . '">'
    . '<label for="app_env">Mode</label><select id="app_env" name="app_env"><option value="production">Production (real users, HTTPS only)</option><option value="local"' . ($v('app_env') === 'local' ? ' selected' : '') . '>Testing</option></select></div>'
    . '<div class="card"><h2>3. Database</h2><p class="muted">Create an empty database and a user in cPanel → MySQL Databases, and add the user to it with ALL PRIVILEGES. cPanel adds your account name as a prefix, like <code>cpuser_couple</code>.</p>'
    . '<label for="db_host">Host</label><select id="db_host" name="db_host"><option>localhost</option><option' . ($v('db_host') === '127.0.0.1' ? ' selected' : '') . '>127.0.0.1</option></select>'
    . '<label for="db_name">Database name</label><input id="db_name" name="db_name" required value="' . h($v('db_name')) . '">'
    . '<label for="db_user">Database user</label><input id="db_user" name="db_user" required value="' . h($v('db_user')) . '">'
    . '<label for="db_pass">Database password</label><input id="db_pass" name="db_pass" type="password"></div>'
    . '<div class="card"><h2>4. Private storage</h2><label for="storage">Folder for encrypted photos and logs (outside public_html)</label><input id="storage" name="storage" required value="' . h($v('storage', $defaultStorage)) . '"></div>'
    . '<div class="card"><h2>5. Your admin account</h2><label for="admin_name">Name</label><input id="admin_name" name="admin_name" required maxlength="60" value="' . h($v('admin_name')) . '">'
    . '<label for="admin_email">Email</label><input id="admin_email" name="admin_email" type="email" required value="' . h($v('admin_email')) . '">'
    . '<label for="admin_pass">Password (12+ characters, a letter and a number)</label><input id="admin_pass" name="admin_pass" type="password" required minlength="12" autocomplete="new-password"></div>'
    . '<p><button type="submit"' . ($blocking ? ' disabled' : '') . '>Install</button></p>'
    . '<p class="muted">The installer generates the encryption keys, writes <code>.env</code> (readable only by your account), creates the tables, loads the starter games and themes, and deletes itself. Email, payments and push are added afterwards in <code>.env</code>.</p></form>');
