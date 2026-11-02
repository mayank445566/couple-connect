<?php
declare(strict_types=1);
// cPanel cron (every minute):  * * * * * /usr/local/bin/php /home/USER/couple-connect/backend/bin/cron.php >/dev/null 2>&1

use App\Core\Database;
use App\Services\Events;
use App\Services\MediaVault;

require dirname(__DIR__) . '/src/bootstrap.php';

$lock = fopen(sys_get_temp_dir() . '/couple-connect-cron.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit; // previous run still going
}

// 1. Disappearing messages: hard-delete expired rows and their media
$expired = Database::all('SELECT id, couple_id, media_id FROM messages WHERE expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP() LIMIT 500');
foreach ($expired as $m) {
    Database::run('DELETE FROM messages WHERE id = ?', [$m['id']]);
    if ($m['media_id']) {
        Database::run('UPDATE media SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND NOT EXISTS (SELECT 1 FROM messages WHERE media_id = ?)', [$m['media_id'], $m['media_id']]);
    }
    Events::emit((int)$m['couple_id'], null, 'message.expired', ['id' => (int)$m['id']]);
}

// 2. Physically remove files of deleted media
foreach (Database::all('SELECT id, storage_name FROM media WHERE deleted_at IS NOT NULL AND storage_name <> "" LIMIT 500') as $md) {
    MediaVault::delete($md['storage_name']);
    Database::run('DELETE FROM media WHERE id = ?', [$md['id']]);
}

// 3. Accounts whose deletion grace period (7 days) has passed: remove content, anonymise the row
foreach (Database::all('SELECT id FROM users WHERE deletion_requested_at IS NOT NULL AND deletion_requested_at <= UTC_TIMESTAMP() - INTERVAL 7 DAY AND status <> "deleted" LIMIT 50') as $u) {
    $uid = (int)$u['id'];
    Database::transaction(function () use ($uid) {
        Database::run('DELETE FROM messages WHERE sender_id = ?', [$uid]);
        Database::run('UPDATE media SET deleted_at = UTC_TIMESTAMP() WHERE owner_id = ?', [$uid]);
        Database::run('DELETE FROM auth_tokens WHERE user_id = ?', [$uid]);
        Database::run('DELETE FROM user_codes WHERE user_id = ?', [$uid]);
        Database::run('DELETE FROM presence WHERE user_id = ?', [$uid]);
        Database::run(
            "UPDATE users SET status = 'deleted', email = NULL, phone = NULL, display_name = 'Deleted user', bio = NULL,
                    avatar_media_id = NULL, dob = '1900-01-01', password_hash = ?, active_couple_id = NULL WHERE id = ?",
            [password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $uid]
        );
    });
}

// 4. Housekeeping
Database::run('DELETE FROM rate_limits WHERE reset_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR');
Database::run('DELETE FROM user_codes WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
Database::run("UPDATE pair_requests SET status = 'expired' WHERE status IN ('open','pending_approval') AND expires_at <= UTC_TIMESTAMP()");
Database::run('DELETE FROM auth_tokens WHERE (revoked_at IS NOT NULL AND revoked_at < UTC_TIMESTAMP() - INTERVAL 30 DAY) OR expires_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
Database::run('DELETE FROM couple_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY LIMIT 5000');

// 5. Scheduled love letters: announce when their delivery time arrives
foreach (Database::all('SELECT id, couple_id, author_id FROM notes WHERE deliver_at IS NOT NULL AND deliver_at <= UTC_TIMESTAMP() AND announced_at IS NULL LIMIT 200') as $n) {
    Database::run('UPDATE notes SET announced_at = UTC_TIMESTAMP() WHERE id = ?', [$n['id']]);
    Events::emit((int)$n['couple_id'], (int)$n['author_id'], 'note.created', ['id' => (int)$n['id'], 'kind' => 'letter', 'scheduled' => true]);
}

// 6. Abandoned game invites expire after 24 hours; finished/old OTPs and webhooks are trimmed
Database::run("UPDATE game_sessions SET status = 'stopped' WHERE status = 'invited' AND created_at < UTC_TIMESTAMP() - INTERVAL 24 HOUR");
Database::run('DELETE FROM phone_otps WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');

// Calls: ringing calls nobody answered become missed; drop signalling data of finished calls
\App\Controllers\CallController::expireRinging();
Database::run("DELETE s FROM call_signals s JOIN calls c ON c.id = s.call_id WHERE c.status NOT IN ('ringing','active')");

