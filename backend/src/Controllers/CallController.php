<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Services\CoupleContext;
use App\Services\Events;
use App\Services\Present;

/**
 * Voice and video calls between partners.
 *
 * Media goes peer to peer (WebRTC), or through the TURN server when a direct path is
 * blocked. This server only stores call state and relays the signalling messages
 * (offer, answer, ICE candidates), which contain network details but no audio or video.
 */
final class CallController
{
    public const RING_SECONDS = 45;

    public function start(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('call:user:' . $ctx->userId, 30, 3600);
        $kind = $req->input('kind', 'audio');
        if (!in_array($kind, ['audio', 'video'], true)) {
            throw ApiException::validation(['kind' => 'Choose audio or video.']);
        }
        self::expireRinging();
        $id = Database::transaction(function () use ($ctx, $kind) {
            // One call at a time per couple; lock the couple row so two people calling at once can't both start
            Database::one('SELECT id FROM couples WHERE id = ? FOR UPDATE', [$ctx->id()]);
            $busy = Database::one("SELECT id, caller_id, status FROM calls WHERE couple_id = ? AND status IN ('ringing','active') LIMIT 1", [$ctx->id()]);
            if ($busy) {
                throw new ApiException(409, 'call_in_progress', 'There is already a call going on.', ['call_id' => (string)$busy['id']]);
            }
            $id = Database::insert('INSERT INTO calls (couple_id, caller_id, callee_id, kind) VALUES (?, ?, ?, ?)', [$ctx->id(), $ctx->userId, $ctx->partnerId, $kind]);
            Events::emit($ctx->id(), $ctx->userId, 'call.ringing', ['call_id' => $id, 'kind' => $kind]);
            return $id;
        });
        return Router::status(201, $this->state($this->load($ctx, $id), $ctx) + ['ice_servers' => self::iceServers($ctx->userId)]);
    }

    public function show(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        self::expireRinging();
        return $this->state($this->load($ctx, (int)$req->params['id']), $ctx);
    }

    public function index(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        self::expireRinging();
        $rows = Database::all('SELECT * FROM calls WHERE couple_id = ? ORDER BY id DESC LIMIT 50', [$ctx->id()]);
        return array_map(fn($c) => $this->state($c, $ctx), $rows);
    }

    public function accept(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        self::expireRinging();
        $call = $this->load($ctx, (int)$req->params['id']);
        if ((int)$call['callee_id'] !== $ctx->userId) {
            throw new ApiException(403, 'not_callee', 'Only the person being called can answer.');
        }
        $n = Database::run("UPDATE calls SET status = 'active', answered_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'ringing'", [$call['id']])->rowCount();
        if ($n === 0) {
            throw new ApiException(409, 'call_not_ringing', 'This call has already ended.');
        }
        Events::emit($ctx->id(), $ctx->userId, 'call.accepted', ['call_id' => (int)$call['id']]);
        return $this->state($this->load($ctx, (int)$call['id']), $ctx) + ['ice_servers' => self::iceServers($ctx->userId)];
    }

    public function decline(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $call = $this->load($ctx, (int)$req->params['id']);
        if ((int)$call['callee_id'] !== $ctx->userId) {
            throw new ApiException(403, 'not_callee', 'Only the person being called can decline.');
        }
        return $this->finish($ctx, $call, ['ringing'], 'declined', 'declined');
    }

    /** Hang up. While ringing, the caller cancels (the partner sees a missed call). */
    public function end(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $call = $this->load($ctx, (int)$req->params['id']);
        if ($call['status'] === 'ringing') {
            if ((int)$call['caller_id'] === $ctx->userId) {
                return $this->finish($ctx, $call, ['ringing'], 'cancelled', 'cancelled');
            }
            return $this->finish($ctx, $call, ['ringing'], 'declined', 'declined');
        }
        $reason = $req->input('reason', 'hangup');
        $reason = in_array($reason, ['hangup', 'failed', 'network'], true) ? $reason : 'hangup';
        return $this->finish($ctx, $call, ['active'], 'ended', $reason);
    }

    /** Store an offer, answer or ICE candidate(s) for the other side. */
    public function signal(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('signal:user:' . $ctx->userId, 600, 600);
        $call = $this->load($ctx, (int)$req->params['id']);
        if (!in_array($call['status'], ['ringing', 'active'], true)) {
            throw new ApiException(409, 'call_ended', 'This call has ended.');
        }
        $kind = $req->input('kind');
        if (!in_array($kind, ['offer', 'answer', 'ice', 'renegotiate'], true)) {
            throw ApiException::validation(['kind' => 'Use offer, answer, ice or renegotiate.']);
        }
        $payload = $req->input('payload');
        $items = $kind === 'ice' && is_array($payload) && array_is_list($payload) ? $payload : [$payload];
        if (!$items || count($items) > 50) {
            throw ApiException::validation(['payload' => 'Send between 1 and 50 candidates.']);
        }
        $last = 0;
        foreach ($items as $p) {
            $json = json_encode($p, JSON_UNESCAPED_SLASHES);
            if (!is_array($p) || strlen($json) > 20000) {
                throw ApiException::validation(['payload' => 'Invalid signalling payload.']);
            }
            $last = Database::insert('INSERT INTO call_signals (call_id, from_user_id, kind, payload) VALUES (?, ?, ?, ?)', [$call['id'], $ctx->userId, $kind, $json]);
        }
        Events::ping($ctx->id(), 'call.signal', ['call_id' => (int)$call['id']]);
        return ['stored' => count($items), 'last_id' => $last];
    }

    /** Signals from the partner after a given id. Poll this during call setup if the relay isn't connected. */
    public function signals(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $call = $this->load($ctx, (int)$req->params['id']);
        $after = max(0, (int)($req->query['after'] ?? 0));
        $rows = Database::all('SELECT id, kind, payload, created_at FROM call_signals WHERE call_id = ? AND from_user_id <> ? AND id > ? ORDER BY id LIMIT 200', [$call['id'], $ctx->userId, $after]);
        return [
            'status' => $call['status'],
            'signals' => array_map(fn($r) => ['id' => (int)$r['id'], 'kind' => $r['kind'], 'payload' => json_decode($r['payload'], true)], $rows),
        ];
    }

    public function turn(Request $req, array $user): array
    {
        CoupleContext::require($user);
        RateLimiter::hit('turn:user:' . $user['id'], 60, 3600);
        return ['ice_servers' => self::iceServers((int)$user['id']), 'ttl' => self::turnTtl()];
    }

    /**
     * STUN plus time-limited TURN credentials (coturn "use-auth-secret" / TURN REST API):
     * username = "<expiry>:<user id>", credential = base64(HMAC-SHA1(TURN_SECRET, username)).
     */
    public static function iceServers(int $userId): array
    {
        $servers = [];
        $stun = array_filter(array_map('trim', explode(',', (string)Config::get('STUN_URLS', 'stun:stun.l.google.com:19302'))));
        if ($stun) {
            $servers[] = ['urls' => array_values($stun)];
        }
        $turn = array_filter(array_map('trim', explode(',', (string)Config::get('TURN_URLS', ''))));
        $secret = Config::get('TURN_SECRET');
        if ($turn && $secret) {
            $username = (time() + self::turnTtl()) . ':' . $userId;
            $servers[] = [
                'urls' => array_values($turn),
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
            ];
        }
        return $servers;
    }

    private static function turnTtl(): int
    {
        return Config::int('TURN_TTL_SECONDS', 6 * 3600);
    }

    /** Ringing calls nobody answered become missed. Also run from cron. */
    public static function expireRinging(): void
    {
        $stale = Database::all('SELECT id, couple_id, caller_id FROM calls WHERE status = ? AND created_at < UTC_TIMESTAMP() - INTERVAL ? SECOND LIMIT 50', ['ringing', self::RING_SECONDS]);
        foreach ($stale as $c) {
            $n = Database::run("UPDATE calls SET status = 'missed', end_reason = 'missed', ended_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'ringing'", [$c['id']])->rowCount();
            if ($n) {
                Events::emit((int)$c['couple_id'], (int)$c['caller_id'], 'call.ended', ['call_id' => (int)$c['id'], 'reason' => 'missed']);
            }
        }
        // Active calls whose peers vanished (app killed) are closed after 4 hours
        Database::run("UPDATE calls SET status = 'ended', end_reason = 'timeout', ended_at = UTC_TIMESTAMP() WHERE status = 'active' AND answered_at < UTC_TIMESTAMP() - INTERVAL 4 HOUR");
    }

    private function finish(CoupleContext $ctx, array $call, array $from, string $status, string $reason): array
    {
        $in = implode(',', array_fill(0, count($from), '?'));
        $n = Database::run(
            "UPDATE calls SET status = ?, end_reason = ?, ended_by = ?, ended_at = UTC_TIMESTAMP() WHERE id = ? AND status IN ($in)",
            array_merge([$status, $reason, $ctx->userId, $call['id']], $from)
        )->rowCount();
        if ($n > 0) {
            Events::emit($ctx->id(), $ctx->userId, 'call.ended', ['call_id' => (int)$call['id'], 'reason' => $reason]);
            Database::run('DELETE FROM call_signals WHERE call_id = ?', [$call['id']]);
        }
        return $this->state($this->load($ctx, (int)$call['id']), $ctx);
    }

    private function load(CoupleContext $ctx, int $id): array
    {
        $c = Database::one('SELECT * FROM calls WHERE id = ? AND couple_id = ?', [$id, $ctx->id()]);
        if (!$c) {
            throw new ApiException(404, 'not_found', 'Call not found.');
        }
        return $c;
    }

    private function state(array $c, CoupleContext $ctx): array
    {
        $dur = null;
        if ($c['answered_at']) {
            $endTs = $c['ended_at'] ? strtotime($c['ended_at'] . ' UTC') : time();
            $dur = max(0, $endTs - strtotime($c['answered_at'] . ' UTC'));
        }
        return [
            'id' => (int)$c['id'],
            'kind' => $c['kind'],
            'status' => $c['status'],
            'outgoing' => (int)$c['caller_id'] === $ctx->userId,
            'caller_id' => (int)$c['caller_id'],
            'callee_id' => (int)$c['callee_id'],
            'end_reason' => $c['end_reason'],
            'created_at' => Present::iso($c['created_at']),
            'answered_at' => Present::iso($c['answered_at']),
            'ended_at' => Present::iso($c['ended_at']),
            'duration_seconds' => $dur,
        ];
    }
}
