<?php
declare(strict_types=1);
// Admin accounts from the command line (cPanel Terminal or SSH).
//   php bin/admin.php create owner@example.com "Your Name" owner
//   php bin/admin.php reset-password owner@example.com
//   php bin/admin.php reset-2fa owner@example.com
//   php bin/admin.php list
// Roles: owner, moderator, finance, content. New and reset passwords are temporary and must be changed at first sign-in.

use App\Core\Auth;
use App\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$cmd = $argv[1] ?? '';
$email = strtolower(trim($argv[2] ?? ''));

function temp_password(): string
{
    return rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Kq'), '=') . '7a';
}

function audit_cli(string $action, ?int $id, string $email): void
{
    Database::run('INSERT INTO admin_audit_log (admin_id, admin_email, action, target_type, target_id, ip) VALUES (NULL, ?, ?, \'admin\', ?, \'cli\')', ['cli:' . $email, $action, $id]);
}

switch ($cmd) {
    case 'create':
        $name = trim($argv[3] ?? '');
        $role = $argv[4] ?? 'owner';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !in_array($role, ['owner', 'moderator', 'finance', 'content'], true)) {
            fwrite(STDERR, "Usage: php bin/admin.php create EMAIL \"NAME\" [owner|moderator|finance|content]\n");
            exit(1);
        }
        if (Database::value('SELECT 1 FROM admin_users WHERE email = ?', [$email])) {
            fwrite(STDERR, "An admin with that email already exists.\n");
            exit(1);
        }
        $pw = temp_password();
        $id = Database::insert('INSERT INTO admin_users (email, name, password_hash, role, must_change_password) VALUES (?, ?, ?, ?, 1)', [$email, mb_substr($name, 0, 60), Auth::hashPassword($pw), $role]);
        audit_cli('admin_created', $id, $email);
        echo "Created $role $email\nTemporary password: $pw\nSign in at /admin, then set your own password and two-step sign-in.\n";
        break;
    case 'reset-password':
        $id = Database::value('SELECT id FROM admin_users WHERE email = ?', [$email]);
        if (!$id) {
            fwrite(STDERR, "No admin with that email.\n");
            exit(1);
        }
        $pw = temp_password();
        Database::run('UPDATE admin_users SET password_hash = ?, must_change_password = 1, failed_logins = 0, locked_until = NULL, status = \'active\' WHERE id = ?', [Auth::hashPassword($pw), $id]);
        Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$id]);
        audit_cli('password_reset', (int)$id, $email);
        echo "Temporary password: $pw\n";
        break;
    case 'reset-2fa':
        $id = Database::value('SELECT id FROM admin_users WHERE email = ?', [$email]);
        if (!$id) {
            fwrite(STDERR, "No admin with that email.\n");
            exit(1);
        }
        Database::run('UPDATE admin_users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?', [$id]);
        Database::run('UPDATE admin_sessions SET revoked_at = UTC_TIMESTAMP() WHERE admin_id = ? AND revoked_at IS NULL', [$id]);
        audit_cli('2fa_reset', (int)$id, $email);
        echo "Two-step sign-in removed for $email. They will be asked to set it up again.\n";
        break;
    case 'list':
        foreach (Database::all('SELECT email, name, role, status, totp_enabled_at, last_login_at FROM admin_users ORDER BY id') as $a) {
            printf("%-32s %-10s %-9s 2FA:%-3s last: %s\n", $a['email'], $a['role'], $a['status'], $a['totp_enabled_at'] ? 'on' : 'off', $a['last_login_at'] ?? 'never');
        }
        break;
    default:
        fwrite(STDERR, "Commands: create, reset-password, reset-2fa, list\n");
        exit(1);
}
