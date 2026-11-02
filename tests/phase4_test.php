<?php
declare(strict_types=1);
/**
 * Phase 4 end-to-end test: relay tickets, push tokens and notification rules, calls and signalling, TURN credentials.
 * Server needs PUSH_DRIVER=log, RELAY_SECRET, RELAY_PUBLIC_URL, TURN_URLS and TURN_SECRET set.
 * Runs on the same machine as the API (reads backend/.env and storage/logs/push.log, and ages presence rows to
 * simulate a partner who has closed the app).
 * Usage: API=http://127.0.0.1:8088 php tests/phase4_test.php
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/../backend/src/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\Notifier;

$PUSH_LOG = Config::storage('logs') . '/push.log';
$run = bin2hex(random_bytes(3));
[$A, $aId] = makeUser("p4a.$run@example.com", 'Kabir');
[$B, $bId] = makeUser("p4b.$run@example.com", 'Meera');
[$C] = makeUser("p4c.$run@example.com", 'Outsider');

function pushesFor(int $userId): array
{
    global $PUSH_LOG;
    clearstatcache();
    $out = [];
    foreach (explode("\n", (string)@file_get_contents($GLOBALS['PUSH_LOG'])) as $l) {
        $j = json_decode($l, true);
        if ($j && $j['to_user'] === $userId) {
            $out[] = $j;
        }
    }
    return $out;
}
function away(int $userId): void
{
    Database::run('UPDATE presence SET last_seen_at = UTC_TIMESTAMP() - INTERVAL 5 MINUTE WHERE user_id = ?', [$userId]);
}

echo "Relay tickets\n";
$r = call('POST', '/realtime/ticket', null, $A);
check('ticket needs a partner', $r['status'] === 409);
pair($A, $B);
$r = call('POST', '/realtime/ticket', null, $A);
$t = $r['json']['data'];
$parts = explode('.', (string)$t['ticket']);
check('ticket issued with relay url', str_starts_with((string)$t['url'], 'ws') && count($parts) === 5 && (int)$parts[0] === $aId);
check('ticket signature verifies with shared secret', hash_equals(hash_hmac('sha256', implode('.', array_slice($parts, 0, 4)), Config::get('RELAY_SECRET')), $parts[4]));

echo "Push tokens and notification rules\n";
$r = call('POST', '/me/push-token', ['token' => 'bad token!'], $B);
check('invalid push token rejected', $r['status'] === 422);
$r = call('POST', '/me/push-token', ['token' => "fcmTokenB_$run:APA91b-xyz", 'platform' => 'android'], $B);
check('push token saved', $r['json']['data']['registered'] === true);
$before = count(pushesFor($bId));
call('POST', '/messages', ['client_id' => uuid(), 'body' => 'secret plans for saturday'], $A);
check('no push while partner has the app open', count(pushesFor($bId)) === $before);
away($bId);
call('POST', '/messages', ['client_id' => uuid(), 'body' => 'secret plans for saturday'], $A);
$p = pushesFor($bId);
$last = end($p);
check('push sent when partner is away', count($p) === $before + 1 && $last['channel'] === 'messages');
check('no message text in push by default', $last['body'] === 'New message' && !str_contains(json_encode($last), 'saturday'));
call('PATCH', '/me/preferences', ['show_preview' => true], $B);
away($bId);
call('POST', '/messages', ['client_id' => uuid(), 'body' => 'dinner at 8?'], $A);
$p = pushesFor($bId);
check('preview shown only after opting in', end($p)['body'] === 'dinner at 8?');
call('PATCH', '/me/preferences', ['notify_messages' => false], $B);
away($bId);
$n = count(pushesFor($bId));
call('POST', '/taps', ['kind' => 'hug'], $A);
check('message notifications can be switched off', count(pushesFor($bId)) === $n);
call('PATCH', '/me/preferences', ['notify_messages' => true, 'show_preview' => false], $B);
check('quiet hours wrap midnight', Notifier::inQuietHours('23:00', '07:00', new DateTimeImmutable('2026-01-01 02:30')) && !Notifier::inQuietHours('23:00', '07:00', new DateTimeImmutable('2026-01-01 12:00')));

echo "Calls\n";
$r = call('POST', '/calls', ['kind' => 'video'], $C);
check('call needs a partner', $r['status'] === 409);
$r = call('POST', '/calls', ['kind' => 'video'], $A);
$call = $r['json']['data'];
$cid = $call['id'];
check('call starts ringing', $r['status'] === 201 && $call['status'] === 'ringing' && $call['outgoing'] === true);
$turn = end($call['ice_servers']);
check('TURN credentials are time-limited HMAC', isset($turn['username']) && hash_equals(base64_encode(hash_hmac('sha1', $turn['username'], Config::get('TURN_SECRET'), true)), $turn['credential']) && (int)explode(':', $turn['username'])[0] > time());
$p = pushesFor($bId);
check('incoming call push rings even if app is open', end($p)['channel'] === 'calls' && end($p)['data']['call_id'] === (string)$cid);
$r = call('POST', '/calls', ['kind' => 'audio'], $B);
check('only one call at a time', $r['status'] === 409 && $r['json']['error']['code'] === 'call_in_progress');
$r = call('POST', "/calls/$cid/accept", null, $A);
check('caller cannot answer own call', $r['status'] === 403);
$r = call('GET', '/sync?since=0', null, $B);
check('callee sees call.ringing in sync', (bool)array_filter($r['json']['data']['events'], fn($e) => $e['type'] === 'call.ringing' && $e['payload']['call_id'] === $cid));
call('POST', "/calls/$cid/signal", ['kind' => 'offer', 'payload' => ['type' => 'offer', 'sdp' => "v=0\r\n"]], $A);
$r = call('POST', "/calls/$cid/accept", null, $B);
check('callee answers', $r['json']['data']['status'] === 'active');
$s = call('GET', "/calls/$cid/signals?after=0", null, $B)['json']['data']['signals'];
check('callee receives the offer', count($s) === 1 && $s[0]['kind'] === 'offer' && $s[0]['payload']['sdp'] === "v=0\r\n");
check('caller does not receive own signals', call('GET', "/calls/$cid/signals?after=0", null, $A)['json']['data']['signals'] === []);
call('POST', "/calls/$cid/signal", ['kind' => 'answer', 'payload' => ['type' => 'answer', 'sdp' => "v=0\r\na"]], $B);
$r = call('POST', "/calls/$cid/signal", ['kind' => 'ice', 'payload' => [['candidate' => 'candidate:1 1 udp 1 1.2.3.4 5 typ host', 'sdpMid' => '0', 'sdpMLineIndex' => 0], ['candidate' => 'candidate:2', 'sdpMid' => '0', 'sdpMLineIndex' => 0]]], $B);
check('ICE candidates batched', $r['json']['data']['stored'] === 2);
$s = call('GET', "/calls/$cid/signals?after=0", null, $A)['json']['data']['signals'];
$afterId = $s[0]['id'];
check('caller gets answer then candidates in order', array_column($s, 'kind') === ['answer', 'ice', 'ice']);
check('signals can be fetched incrementally', count(call('GET', "/calls/$cid/signals?after=$afterId", null, $A)['json']['data']['signals']) === 2);
check('outsider cannot read the call', call('GET', "/calls/$cid", null, $C)['status'] === 409);
sleep(1);
$r = call('POST', "/calls/$cid/end", null, $A);
check('hang up ends call with duration', $r['json']['data']['status'] === 'ended' && $r['json']['data']['duration_seconds'] >= 1);
check('signals deleted after the call', (int)Database::value('SELECT COUNT(*) FROM call_signals WHERE call_id = ?', [$cid]) === 0);
check('no signalling after end', call('POST', "/calls/$cid/signal", ['kind' => 'ice', 'payload' => ['candidate' => 'x']], $A)['status'] === 409);

$cid2 = call('POST', '/calls', ['kind' => 'audio'], $B)['json']['data']['id'];
$r = call('POST', "/calls/$cid2/decline", null, $A);
check('decline', $r['json']['data']['status'] === 'declined');
$cid3 = call('POST', '/calls', ['kind' => 'audio'], $A)['json']['data']['id'];
away($bId);
$n = count(pushesFor($bId));
call('POST', "/calls/$cid3/end", null, $A);
$p = pushesFor($bId);
check('caller cancel = missed call notice for partner', call('GET', "/calls/$cid3", null, $B)['json']['data']['status'] === 'cancelled' && count($p) === $n + 1 && str_contains(end($p)['body'], 'Missed call'));
$cid4 = call('POST', '/calls', ['kind' => 'audio'], $A)['json']['data']['id'];
Database::run('UPDATE calls SET created_at = UTC_TIMESTAMP() - INTERVAL 2 MINUTE WHERE id = ?', [$cid4]);
check('unanswered call becomes missed', call('GET', "/calls/$cid4", null, $B)['json']['data']['status'] === 'missed');
$h = call('GET', '/calls', null, $B)['json']['data'];
check('call history', count($h) === 4 && $h[0]['id'] === $cid4);
check('turn endpoint', count(call('GET', '/calls/turn', null, $B)['json']['data']['ice_servers']) === 2);

echo "Sign-out stops pushes\n";
call('POST', '/auth/logout', null, $B);
away($bId);
$n = count(pushesFor($bId));
[$B2] = [call('POST', '/auth/login', ['email' => "p4b.$run@example.com", 'password' => 'Sunrise2026'])['json']['data']['token']];
away($bId);
call('POST', '/messages', ['client_id' => uuid(), 'body' => 'are you there'], $A);
check('signed-out device gets no push', count(pushesFor($bId)) === $n);

finish();
