<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Validator;
use App\Services\CoupleContext;
use App\Services\Events;
use App\Services\Present;

final class ProfileController
{
    public function me(Request $req, array $user): array
    {
        return Present::me(Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]));
    }

    public function update(Request $req, array $user): array
    {
        $d = Validator::check($req->json(), [
            'display_name' => 'string,min:1,max:60',
            'bio' => 'string,max:160',
            'relationship_status' => 'in:dating|engaged|married|long_distance|other',
            'last_seen_visibility' => 'in:partner|nobody',
            'read_receipts' => 'bool',
            'avatar_media_id' => 'int',
        ]);
        if (isset($d['avatar_media_id'])) {
            $ok = Database::value("SELECT 1 FROM media WHERE id = ? AND owner_id = ? AND kind IN ('avatar','image') AND deleted_at IS NULL", [$d['avatar_media_id'], $user['id']]);
            if (!$ok) {
                throw ApiException::validation(['avatar_media_id' => 'Upload the picture first.']);
            }
        }
        $sets = [];
        $params = [];
        foreach ($d as $k => $v) {
            $sets[] = "$k = ?";
            $params[] = is_bool($v) ? (int)$v : $v;
        }
        if ($sets) {
            $params[] = $user['id'];
            Database::run('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            if ($user['active_couple_id'] !== null) {
                Events::emit((int)$user['active_couple_id'], (int)$user['id'], 'profile.updated', ['user_id' => (int)$user['id']]);
            }
        }
        return $this->me($req, $user);
    }

    public function changePassword(Request $req, array $user): array
    {
        $d = Validator::check($req->json(), ['current_password' => 'required,string', 'new_password' => 'required,password']);
        RateLimiter::hit('chpw:user:' . $user['id'], 5, 900);
        $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($d['current_password'], $hash)) {
            throw new ApiException(403, 'wrong_password', 'Current password is incorrect.');
        }
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($d['new_password']), $user['id']]);
        // Keep this device signed in, sign out the others
        Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND id <> ? AND revoked_at IS NULL', [$user['id'], $user['token_id']]);
        return ['changed' => true];
    }

    /**
     * Requests deletion. The account is locked out of pairing immediately, unpaired,
     * and purged by cron after 7 days unless the user signs in again.
     */
    public function delete(Request $req, array $user): array
    {
        RateLimiter::hit('delete:user:' . $user['id'], 5, 900);
        $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify((string)$req->input('password', ''), $hash)) {
            throw new ApiException(403, 'wrong_password', 'Password is incorrect.');
        }
        Database::transaction(function () use ($user) {
            if ($user['active_couple_id'] !== null) {
                $ctx = CoupleContext::require($user);
                Database::run("UPDATE couples SET status = 'ended', ended_at = UTC_TIMESTAMP(), ended_by = ? WHERE id = ?", [$ctx->userId, $ctx->id()]);
                Database::run('UPDATE users SET active_couple_id = NULL WHERE id IN (?, ?)', [$ctx->userId, $ctx->partnerId]);
                Events::emit($ctx->id(), $ctx->userId, 'couple.unpaired', ['reason' => 'account_deleted']);
            }
            Database::run('UPDATE users SET deletion_requested_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
            Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$user['id']]);
        });
        return ['deletion_scheduled' => true, 'purge_after_days' => 7];
    }

    /** Machine-readable export of everything this user created. */
    public function export(Request $req, array $user): array
    {
        RateLimiter::hit('export:user:' . $user['id'], 3, 3600);
        $me = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $messages = Database::all(
            'SELECT id, couple_id, type, body, media_id, reply_to_id, created_at, edited_at, deleted_at FROM messages WHERE sender_id = ? ORDER BY id',
            [$user['id']]
        );
        return [
            'exported_at' => gmdate('c'),
            'profile' => Present::me($me),
            'couples' => Database::all('SELECT id, public_code, paired_at, ended_at, status FROM couples WHERE user_a_id = ? OR user_b_id = ?', [$user['id'], $user['id']]),
            'sessions' => Database::all('SELECT device_name, platform, ip, created_at, last_used_at, revoked_at FROM auth_tokens WHERE user_id = ?', [$user['id']]),
            'messages_sent' => array_map(fn($m) => $m['deleted_at'] ? ['id' => $m['id'], 'deleted' => true] : $m, $messages),
            'media' => Database::all('SELECT id, kind, mime, size_bytes, created_at FROM media WHERE owner_id = ? AND deleted_at IS NULL', [$user['id']]),
        ];
    }
}
