<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Services\Codes;
use App\Services\CoupleContext;
use App\Services\Events;
use App\Services\Present;

/**
 * Pairing requires two independent approvals:
 *  1. Inviter creates an invite (6-digit code or QR token, 15 min, single use).
 *  2. Invitee submits the code and sees the inviter's name before confirming.
 *  3. Inviter sees the invitee's name and must accept. Only then is a couple created.
 * A leaked code alone can never connect a stranger.
 */
final class CoupleController
{
    private const INVITE_TTL_MINUTES = 15;
    private const APPROVAL_TTL_HOURS = 48;

    public function createInvite(Request $req, array $user): array
    {
        $this->assertCanPair($user);
        RateLimiter::hit('invite:user:' . $user['id'], 10, 3600);
        Database::run(
            "UPDATE pair_requests SET status = 'cancelled', responded_at = UTC_TIMESTAMP()
              WHERE inviter_id = ? AND status IN ('open','pending_approval')",
            [$user['id']]
        );
        // Codes are unique among live invites; retry on the rare collision
        for ($i = 0; $i < 5; $i++) {
            $code = Codes::sixDigits();
            $hash = Codes::hmac('pair:' . $code);
            if (!Database::value("SELECT 1 FROM pair_requests WHERE code_hash = ? AND status = 'open' AND expires_at > UTC_TIMESTAMP()", [$hash])) {
                break;
            }
        }
        $qrToken = Auth::newToken();
        $id = Database::insert(
            'INSERT INTO pair_requests (inviter_id, code_hash, qr_token_hash, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
            [$user['id'], $hash, Codes::hmac('qr:' . $qrToken), self::INVITE_TTL_MINUTES]
        );
        $row = Database::one('SELECT expires_at FROM pair_requests WHERE id = ?', [$id]);
        return Router::status(201, [
            'request_id' => $id,
            'code' => $code,
            'qr_payload' => 'coupleconnect://pair?t=' . $qrToken,
            'share_text' => "Join me on " . Config::get('APP_NAME', 'Couple Connect') . "! My pairing code is $code (valid 15 minutes).",
            'expires_at' => Present::iso($row['expires_at']),
        ]);
    }

    /** Invitee step: submit a code or QR token. Moves the invite to pending_approval. */
    public function join(Request $req, array $user): array
    {
        $this->assertCanPair($user);
        RateLimiter::hit('join:user:' . $user['id'], 10, 900);
        RateLimiter::hit('join:ip:' . $req->ip(), 30, 900);
        $code = trim((string)$req->input('code', ''));
        $qr = trim((string)$req->input('qr_token', ''));
        if ($qr !== '' && str_starts_with($qr, 'coupleconnect://')) {
            parse_str((string)parse_url($qr, PHP_URL_QUERY), $q);
            $qr = (string)($q['t'] ?? '');
        }
        if ($code !== '' && preg_match('/^\d{6}$/', $code)) {
            $where = 'code_hash = ?';
            $param = Codes::hmac('pair:' . $code);
        } elseif ($qr !== '' && preg_match('/^[A-Za-z0-9_\-]{20,64}$/', $qr)) {
            $where = 'qr_token_hash = ?';
            $param = Codes::hmac('qr:' . $qr);
        } else {
            throw ApiException::validation(['code' => 'Enter the 6-digit code from your partner.']);
        }

        return Database::transaction(function () use ($where, $param, $user) {
            $pr = Database::one(
                "SELECT * FROM pair_requests WHERE $where AND status = 'open' AND expires_at > UTC_TIMESTAMP() FOR UPDATE",
                [$param]
            );
            if ($pr === null) {
                throw new ApiException(404, 'invalid_code', 'That code is invalid or has expired. Ask your partner for a new one.');
            }
            if ((int)$pr['inviter_id'] === (int)$user['id']) {
                throw new ApiException(422, 'own_code', 'This is your own code. Share it with your partner instead.');
            }
            $inviter = Database::one('SELECT * FROM users WHERE id = ?', [$pr['inviter_id']]);
            if ($inviter['status'] !== 'active' || $inviter['active_couple_id'] !== null || $this->blockedBetween((int)$inviter['id'], (int)$user['id'])) {
                throw new ApiException(404, 'invalid_code', 'That code is invalid or has expired. Ask your partner for a new one.');
            }
            // The invitee can only have one outgoing request at a time
            Database::run(
                "UPDATE pair_requests SET status = 'cancelled', responded_at = UTC_TIMESTAMP() WHERE invitee_id = ? AND status = 'pending_approval'",
                [$user['id']]
            );
            Database::run(
                "UPDATE pair_requests SET status = 'pending_approval', invitee_id = ?, joined_at = UTC_TIMESTAMP(),
                        expires_at = UTC_TIMESTAMP() + INTERVAL ? HOUR WHERE id = ?",
                [$user['id'], self::APPROVAL_TTL_HOURS, $pr['id']]
            );
            return [
                'request_id' => (int)$pr['id'],
                'status' => 'pending_approval',
                'inviter' => Present::partner($inviter),
                'message' => 'Waiting for ' . $inviter['display_name'] . ' to accept.',
            ];
        });
    }

    /** Lists the caller's open invite, requests waiting for their approval, and their outgoing request. */
    public function requests(Request $req, array $user): array
    {
        $this->expireOld();
        $incoming = Database::all(
            "SELECT pr.id, pr.joined_at, pr.expires_at, u.id AS uid, u.display_name, u.relationship_status, u.avatar_media_id, u.bio
               FROM pair_requests pr JOIN users u ON u.id = pr.invitee_id
              WHERE pr.inviter_id = ? AND pr.status = 'pending_approval' ORDER BY pr.id DESC",
            [$user['id']]
        );
        $outgoing = Database::one(
            "SELECT pr.id, pr.status, pr.expires_at, u.id AS uid, u.display_name, u.relationship_status, u.avatar_media_id, u.bio
               FROM pair_requests pr JOIN users u ON u.id = pr.inviter_id
              WHERE pr.invitee_id = ? ORDER BY pr.id DESC LIMIT 1",
            [$user['id']]
        );
        $open = Database::one("SELECT id, expires_at FROM pair_requests WHERE inviter_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$user['id']]);
        $shape = fn($r) => Present::partner(['id' => $r['uid']] + $r);
        return [
            'open_invite' => $open ? ['request_id' => (int)$open['id'], 'expires_at' => Present::iso($open['expires_at'])] : null,
            'incoming' => array_map(fn($r) => [
                'request_id' => (int)$r['id'],
                'from' => $shape($r),
                'joined_at' => Present::iso($r['joined_at']),
                'expires_at' => Present::iso($r['expires_at']),
            ], $incoming),
            'outgoing' => $outgoing ? [
                'request_id' => (int)$outgoing['id'],
                'status' => $outgoing['status'],
                'to' => $shape($outgoing),
            ] : null,
        ];
    }

    /** Inviter's final approval. Creates the couple atomically. */
    public function accept(Request $req, array $user): array
    {
        $id = (int)$req->params['id'];
        $coupleId = Database::transaction(function () use ($id, $user) {
            $pr = Database::one(
                "SELECT * FROM pair_requests WHERE id = ? AND inviter_id = ? AND status = 'pending_approval' AND expires_at > UTC_TIMESTAMP() FOR UPDATE",
                [$id, $user['id']]
            );
            if ($pr === null) {
                throw new ApiException(404, 'not_found', 'This request is no longer available.');
            }
            $a = (int)$pr['inviter_id'];
            $b = (int)$pr['invitee_id'];
            // Lock both users in a fixed order to avoid deadlocks and double pairing
            $users = Database::all('SELECT id, status, active_couple_id FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [min($a, $b), max($a, $b)]);
            foreach ($users as $u) {
                if ($u['status'] !== 'active' || $u['active_couple_id'] !== null) {
                    Database::run("UPDATE pair_requests SET status = 'cancelled', responded_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
                    throw new ApiException(409, 'unavailable', 'One of you is already connected to someone else.');
                }
            }
            $coupleId = Database::insert(
                'INSERT INTO couples (public_code, user_a_id, user_b_id) VALUES (?, ?, ?)',
                [$this->newCoupleCode(), $a, $b]
            );
            Database::run('UPDATE users SET active_couple_id = ? WHERE id IN (?, ?)', [$coupleId, $a, $b]);
            Database::run("UPDATE pair_requests SET status = 'accepted', responded_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
            // Any other live requests involving either person are void now
            Database::run(
                "UPDATE pair_requests SET status = 'cancelled', responded_at = UTC_TIMESTAMP()
                  WHERE status IN ('open','pending_approval') AND (inviter_id IN (?, ?) OR invitee_id IN (?, ?))",
                [$a, $b, $a, $b]
            );
            Events::emit($coupleId, (int)$user['id'], 'couple.paired', ['couple_id' => $coupleId]);
            return $coupleId;
        });
        $user['active_couple_id'] = $coupleId;
        return $this->show($req, $user);
    }

    public function reject(Request $req, array $user): array
    {
        $n = Database::run(
            "UPDATE pair_requests SET status = 'rejected', responded_at = UTC_TIMESTAMP()
              WHERE id = ? AND inviter_id = ? AND status = 'pending_approval'",
            [(int)$req->params['id'], $user['id']]
        )->rowCount();
        if ($n === 0) {
            throw new ApiException(404, 'not_found', 'This request is no longer available.');
        }
        return ['rejected' => true];
    }

    /** Either side may withdraw an invite or a pending request. */
    public function cancel(Request $req, array $user): array
    {
        $n = Database::run(
            "UPDATE pair_requests SET status = 'cancelled', responded_at = UTC_TIMESTAMP()
              WHERE id = ? AND (inviter_id = ? OR invitee_id = ?) AND status IN ('open','pending_approval')",
            [(int)$req->params['id'], $user['id'], $user['id']]
        )->rowCount();
        if ($n === 0) {
            throw new ApiException(404, 'not_found', 'This request is no longer available.');
        }
        return ['cancelled' => true];
    }

    public function show(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $c = $ctx->couple;
        $partner = Database::one('SELECT * FROM users WHERE id = ?', [$ctx->partnerId]);
        $start = $c['relationship_start'] ?? substr($c['paired_at'], 0, 10);
        return [
            'couple_id' => (int)$c['id'],
            'couple_code' => $c['public_code'],
            'partner' => Present::partner($partner),
            'paired_at' => Present::iso($c['paired_at']),
            'relationship_start' => $c['relationship_start'],
            'anniversary_date' => $c['anniversary_date'],
            'next_meeting_at' => Present::iso($c['next_meeting_at']),
            'days_together' => (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable('today'))->days,
            'settings' => [
                'disappearing_seconds' => (int)$c['disappearing_seconds'],
                'wallpaper' => $c['wallpaper'],
            ],
        ];
    }

    public function update(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $d = Validator::check($req->json(), [
            'relationship_start' => 'date',
            'anniversary_date' => 'date',
            'next_meeting_at' => 'string,max:30',
            'disappearing_seconds' => 'int,in:0|3600|86400|604800',
            'wallpaper' => 'string,max:100',
        ]);
        $sets = [];
        $params = [];
        foreach (['relationship_start', 'anniversary_date', 'disappearing_seconds', 'wallpaper'] as $f) {
            if (array_key_exists($f, $d)) {
                $sets[] = "$f = ?";
                $params[] = $d[$f];
            }
        }
        if (isset($d['next_meeting_at'])) {
            $ts = strtotime($d['next_meeting_at']);
            if ($ts === false) {
                throw ApiException::validation(['next_meeting_at' => 'Use an ISO 8601 date and time.']);
            }
            $sets[] = 'next_meeting_at = ?';
            $params[] = gmdate('Y-m-d H:i:s', $ts);
        }
        if ($sets) {
            $params[] = $ctx->id();
            Database::run('UPDATE couples SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            Events::emit($ctx->id(), $ctx->userId, 'couple.updated', array_keys($d));
        }
        return $this->show($req, $user);
    }

    /** Ends the connection. Requires the password; optional block prevents re-pairing. */
    public function unpair(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $password = (string)$req->input('password', '');
        $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($password, $hash)) {
            throw new ApiException(403, 'wrong_password', 'Password is incorrect.');
        }
        $block = (bool)$req->input('block', false);
        Database::transaction(function () use ($ctx, $block) {
            Database::run("UPDATE couples SET status = 'ended', ended_at = UTC_TIMESTAMP(), ended_by = ? WHERE id = ?", [$ctx->userId, $ctx->id()]);
            Database::run('UPDATE users SET active_couple_id = NULL WHERE id IN (?, ?)', [$ctx->userId, $ctx->partnerId]);
            if ($block) {
                Database::run('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)', [$ctx->userId, $ctx->partnerId]);
            }
            Events::emit($ctx->id(), $ctx->userId, 'couple.unpaired', []);
        });
        return ['unpaired' => true, 'blocked' => $block];
    }

    private function assertCanPair(array $user): void
    {
        if ($user['email_verified_at'] === null) {
            throw new ApiException(403, 'email_not_verified', 'Verify your email before connecting with a partner.');
        }
        if ($user['active_couple_id'] !== null) {
            throw new ApiException(409, 'already_paired', 'You are already connected with a partner.');
        }
    }

    private function blockedBetween(int $a, int $b): bool
    {
        return (bool)Database::value(
            'SELECT 1 FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)',
            [$a, $b, $b, $a]
        );
    }

    private function expireOld(): void
    {
        Database::run("UPDATE pair_requests SET status = 'expired' WHERE status IN ('open','pending_approval') AND expires_at <= UTC_TIMESTAMP()");
    }

    private function newCoupleCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I
        do {
            $code = 'CC-';
            for ($i = 0; $i < 7; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Database::value('SELECT 1 FROM couples WHERE public_code = ?', [$code]));
        return $code;
    }
}
