<?php
declare(strict_types=1);
/**
 * End-to-end API test with real accounts against a running server + MySQL.
 * Usage: API=http://127.0.0.1:8088 MAIL_LOG=backend/storage/logs/mail.log php tests/api_test.php
 */

$API = rtrim(getenv('API') ?: 'http://127.0.0.1:8088', '/');
$MAIL_LOG = getenv('MAIL_LOG') ?: __DIR__ . '/../backend/storage/logs/mail.log';
$pass = 0;
$fail = 0;

function call(string $method, string $path, ?array $body = null, ?string $token = null, array $extraHeaders = [], ?array $multipart = null): array
{
    global $API;
    $ch = curl_init($API . $path);
    $headers = $extraHeaders;
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    if ($multipart !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $rawHeaders = substr($raw, 0, $hsize);
    $content = substr($raw, $hsize);
    return ['status' => $status, 'json' => json_decode($content, true), 'raw' => $content, 'headers' => $rawHeaders];
}

function check(string $name, bool $cond, mixed $debug = null): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ok   $name\n";
    } else {
        $fail++;
        echo "  FAIL $name\n";
        if ($debug !== null) {
            echo '       ' . substr(json_encode($debug, JSON_UNESCAPED_UNICODE), 0, 600) . "\n";
        }
    }
}

function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function lastCode(string $email): ?string
{
    global $MAIL_LOG;
    clearstatcache();
    $log = (string)@file_get_contents($MAIL_LOG);
    $blocks = array_reverse(explode("---\n", $log));
    foreach ($blocks as $b) {
        if (str_contains($b, "To: $email ") && preg_match('/code is: (\d{6})/', $b, $m)) {
            return $m[1];
        }
    }
    return null;
}

$run = substr(bin2hex(random_bytes(3)), 0, 6);
$emails = ['a' => "asha.$run@example.com", 'b' => "ben.$run@example.com", 'c' => "stranger.$run@example.com", 'd' => "dee.$run@example.com"];
$pw = 'Sunrise2026';

echo "Health\n";
$r = call('GET', '/health');
check('health ok', $r['status'] === 200 && $r['json']['data']['status'] === 'ok', $r);

echo "Registration\n";
$r = call('POST', '/auth/register', ['email' => 'bad', 'password' => 'short', 'display_name' => '', 'dob' => '2015-01-01', 'accept_terms' => true]);
check('invalid input rejected with field errors', $r['status'] === 422 && isset($r['json']['error']['fields']['email'], $r['json']['error']['fields']['password']), $r['json']);
$r = call('POST', '/auth/register', ['email' => "kid.$run@example.com", 'password' => $pw, 'display_name' => 'Kid', 'dob' => date('Y-m-d', strtotime('-16 years')), 'accept_terms' => true]);
check('under-18 rejected', $r['status'] === 422 && isset($r['json']['error']['fields']['dob']), $r['json']);

$tok = [];
$ids = [];
foreach (['a' => 'Asha', 'b' => 'Ben', 'c' => 'Stranger', 'd' => 'Dee'] as $k => $name) {
    $r = call('POST', '/auth/register', ['email' => $emails[$k], 'password' => $pw, 'display_name' => $name, 'dob' => '1996-05-14', 'accept_terms' => true, 'device_name' => "$name phone", 'platform' => 'android']);
    check("register $name", $r['status'] === 201 && !empty($r['json']['data']['token']), $r['json']);
    $tok[$k] = $r['json']['data']['token'] ?? '';
    $ids[$k] = $r['json']['data']['user']['id'] ?? 0;
}
$r = call('POST', '/auth/register', ['email' => $emails['a'], 'password' => $pw, 'display_name' => 'Dup', 'dob' => '1996-05-14', 'accept_terms' => true]);
check('duplicate email rejected', $r['status'] === 422, $r['json']);

echo "Login\n";
$r = call('POST', '/auth/login', ['email' => $emails['a'], 'password' => 'WrongPass1']);
check('wrong password 401', $r['status'] === 401, $r['json']);
$r = call('POST', '/auth/login', ['email' => $emails['a'], 'password' => $pw, 'device_name' => 'Asha tablet', 'platform' => 'android']);
check('login ok', $r['status'] === 200 && !empty($r['json']['data']['token']), $r['json']);
$tokA2 = $r['json']['data']['token'];
$r = call('GET', '/me', null, 'not-a-real-token-xxxxxxxxxxxxxx');
check('bad token 401', $r['status'] === 401);

echo "Email verification\n";
$r = call('POST', '/couple/invite', null, $tok['a']);
check('unverified user cannot invite', $r['status'] === 403 && $r['json']['error']['code'] === 'email_not_verified', $r['json']);
$r = call('POST', '/auth/verify-email', ['code' => '000000'], $tok['a']);
check('wrong code rejected', $r['status'] === 400, $r['json']);
foreach (['a', 'b', 'c'] as $k) {
    $code = lastCode($emails[$k]);
    $r = call('POST', '/auth/verify-email', ['code' => (string)$code], $tok[$k]);
    check("verify $k", $r['status'] === 200 && $r['json']['data']['email_verified'] === true, [$code, $r['json']]);
}

echo "Pairing (two independent approvals)\n";
$r = call('POST', '/couple/invite', null, $tok['a']);
check('A creates invite', $r['status'] === 201 && preg_match('/^\d{6}$/', $r['json']['data']['code']), $r['json']);
$code = $r['json']['data']['code'];
$qr = $r['json']['data']['qr_payload'];
check('QR payload present', str_starts_with($qr, 'coupleconnect://pair?t='));
$r = call('POST', '/couple/join', ['code' => $code], $tok['a']);
check('A cannot join own code', $r['status'] === 422, $r['json']);
$r = call('POST', '/couple/join', ['code' => $code], $tok['b']);
check('B joins with code, sees A', $r['status'] === 200 && $r['json']['data']['inviter']['display_name'] === 'Asha' && $r['json']['data']['status'] === 'pending_approval', $r['json']);
$reqId = $r['json']['data']['request_id'];
$r = call('POST', '/couple/join', ['code' => $code], $tok['c']);
check('stranger cannot reuse the same code', $r['status'] === 404, $r['json']);
$r = call('GET', '/messages', null, $tok['b']);
check('B not paired until A accepts', $r['status'] === 409, $r['json']);
$r = call('POST', "/couple/requests/$reqId/accept", null, $tok['b']);
check('invitee cannot self-accept', $r['status'] === 404, $r['json']);
$r = call('GET', '/couple/requests', null, $tok['a']);
check('A sees incoming request from Ben', ($r['json']['data']['incoming'][0]['from']['display_name'] ?? '') === 'Ben', $r['json']);
$r = call('POST', "/couple/requests/$reqId/accept", null, $tok['a']);
check('A accepts, couple created', $r['status'] === 200 && $r['json']['data']['partner']['display_name'] === 'Ben' && str_starts_with($r['json']['data']['couple_code'], 'CC-'), $r['json']);
$r = call('GET', '/couple', null, $tok['b']);
check('B sees partner A', $r['status'] === 200 && $r['json']['data']['partner']['id'] === $ids['a'], $r['json']);
$r = call('POST', '/couple/invite', null, $tok['a']);
check('paired user cannot invite again', $r['status'] === 409, $r['json']);

echo "Privacy boundaries\n";
$r = call('GET', '/messages', null, $tok['c']);
check('stranger has no chat', $r['status'] === 409, $r['json']);

echo "Messaging\n";
$cid = uuid();
$r1 = call('POST', '/messages', ['client_id' => $cid, 'type' => 'text', 'body' => 'Good morning, sunshine ☀️'], $tok['a']);
check('A sends text', $r1['status'] === 201 && $r1['json']['data']['body'] === 'Good morning, sunshine ☀️', $r1['json']);
$m1 = $r1['json']['data']['id'];
$r = call('POST', '/messages', ['client_id' => $cid, 'type' => 'text', 'body' => 'Good morning, sunshine ☀️'], $tok['a']);
check('retry with same client_id is not duplicated', $r['status'] === 200 && $r['json']['data']['id'] === $m1, $r['json']);
$r = call('POST', '/messages', ['client_id' => uuid(), 'type' => 'text', 'body' => ''], $tok['a']);
check('empty message rejected', $r['status'] === 422);

$r = call('GET', '/sync?since=0', null, $tok['b']);
$sync = $r['json']['data'];
check('B sync receives A message', $r['status'] === 200 && in_array($m1, array_column($sync['messages'], 'id'), true), $sync);
check('B unread count 1', $sync['unread_count'] === 1, $sync);
check('A online in B presence', $sync['partner']['online'] === true, $sync['partner']);
$cursorB = $sync['cursor'];

$r = call('POST', '/messages/delivered', ['up_to_id' => $m1], $tok['b']);
check('B marks delivered', $r['status'] === 200 && $r['json']['data']['updated'] === 1, $r['json']);
$r = call('POST', '/messages/read', ['up_to_id' => $m1], $tok['b']);
check('B marks read, unread 0', $r['json']['data']['unread_count'] === 0, $r['json']);
$r = call('GET', '/messages', null, $tok['a']);
$mine = $r['json']['data']['messages'][0];
check('A sees delivered + read receipts', $mine['delivered_at'] !== null && $mine['read_at'] !== null, $mine);
$cursorA = $r['json']['data']['cursor'];

$r = call('POST', '/presence/typing', ['typing' => true], $tok['a']);
$r = call('GET', "/sync?since=$cursorB", null, $tok['b']);
check('B sees A typing', $r['json']['data']['partner']['typing'] === true, $r['json']['data']['partner']);
$cursorB = $r['json']['data']['cursor'];

$r = call('POST', '/messages', ['client_id' => uuid(), 'type' => 'text', 'body' => 'Morning! Missing you', 'reply_to_id' => $m1], $tok['b']);
check('B replies to A', $r['status'] === 201 && $r['json']['data']['reply_to_id'] === $m1, $r['json']);
$m2 = $r['json']['data']['id'];
$r = call('PATCH', "/messages/$m2", ['body' => 'Morning! Missing you so much'], $tok['b']);
check('B edits own message', $r['status'] === 200 && $r['json']['data']['edited_at'] !== null, $r['json']);
$r = call('PATCH', "/messages/$m2", ['body' => 'hacked'], $tok['a']);
check('A cannot edit B message', $r['status'] === 403, $r['json']);
$r = call('POST', "/messages/$m2/reactions", ['emoji' => '❤️'], $tok['a']);
check('A reacts with heart', $r['status'] === 200 && $r['json']['data']['reactions'][0]['emoji'] === '❤️', $r['json']);
$r = call('POST', "/messages/$m2/reactions", ['emoji' => 'lol'], $tok['a']);
check('non-emoji reaction rejected', $r['status'] === 422);
$r = call('POST', "/messages/$m2/pin", null, $tok['a']);
check('pin message', $r['json']['data']['pinned'] === true, $r['json']);
$r = call('POST', "/messages/$m1/star", null, $tok['b']);
check('B stars message', $r['json']['data']['starred'] === true, $r['json']);
$r = call('GET', '/messages/starred', null, $tok['b']);
check('B starred list has 1', count($r['json']['data']) === 1, $r['json']);
$r = call('GET', '/messages/starred', null, $tok['a']);
check('stars are per-user (A has none)', count($r['json']['data']) === 0, $r['json']);
$r = call('GET', '/messages/pinned', null, $tok['b']);
check('pinned list shared', count($r['json']['data']) === 1, $r['json']);
$r = call('GET', '/messages/search?q=sunshine', null, $tok['b']);
check('search finds message', count($r['json']['data']) === 1 && $r['json']['data'][0]['id'] === $m1, $r['json']);
$r = call('GET', '/messages/search?q=' . urlencode('100%'), null, $tok['b']);
check('search escapes wildcards', $r['status'] === 200 && count($r['json']['data']) === 0, $r['json']);

$r = call('GET', "/sync?since=$cursorA", null, $tok['a']);
$s = $r['json']['data'];
$m2state = array_values(array_filter($s['messages'], fn($m) => $m['id'] === $m2))[0] ?? null;
check('A sync gets edited text + reaction + pin in one state', $m2state && $m2state['body'] === 'Morning! Missing you so much' && $m2state['pinned'] && count($m2state['reactions']) === 1, $s);
$cursorA = $s['cursor'];

$r = call('POST', '/messages', ['client_id' => uuid(), 'forward_of' => $m2], $tok['a']);
check('forward message', $r['status'] === 201 && $r['json']['data']['forwarded'] === true && $r['json']['data']['body'] === 'Morning! Missing you so much', $r['json']);

$r = call('DELETE', "/messages/$m1?scope=me", null, $tok['b']);
check('B deletes A msg for self', $r['json']['data']['deleted'] === 'me', $r['json']);
$r = call('GET', '/messages', null, $tok['b']);
check('hidden for B', !in_array($m1, array_column($r['json']['data']['messages'], 'id'), true));
$r = call('GET', '/messages', null, $tok['a']);
check('still visible for A', in_array($m1, array_column($r['json']['data']['messages'], 'id'), true));
$r = call('GET', "/sync?since=$cursorA", null, $tok['a']);
check('A does not get B private hide event', !in_array('message.hidden', array_column($r['json']['data']['events'], 'type'), true), $r['json']['data']['events']);

$r = call('DELETE', "/messages/$m2?scope=everyone", null, $tok['a']);
check('A cannot delete B message for everyone', $r['status'] === 403, $r['json']);
$r = call('DELETE', "/messages/$m2?scope=everyone", null, $tok['b']);
check('B deletes own message for everyone', $r['json']['data']['deleted'] === 'everyone', $r['json']);
$r = call('GET', '/messages', null, $tok['a']);
$m2a = array_values(array_filter($r['json']['data']['messages'], fn($m) => $m['id'] === $m2))[0];
check('A sees tombstone with no content', $m2a['deleted'] && $m2a['body'] === null && $m2a['type'] === 'deleted', $m2a);

echo "Pagination\n";
for ($i = 1; $i <= 35; $i++) {
    call('POST', '/messages', ['client_id' => uuid(), 'body' => "msg $i"], $tok['a']);
}
$r = call('GET', '/messages?limit=20', null, $tok['b']);
$page1 = $r['json']['data'];
check('page 1 has 20 + has_more', count($page1['messages']) === 20 && $page1['has_more'] === true);
$oldest = end($page1['messages'])['id'];
$r = call('GET', "/messages?limit=20&before=$oldest", null, $tok['b']);
$page2 = $r['json']['data']['messages'];
check('page 2 strictly older, no overlap', $page2 && max(array_column($page2, 'id')) < $oldest && !array_intersect(array_column($page1['messages'], 'id'), array_column($page2, 'id')));

echo "Read receipts privacy\n";
call('PATCH', '/me', ['read_receipts' => false], $tok['b']);
$r = call('GET', '/messages?limit=1', null, $tok['a']);
$lastId = $r['json']['data']['messages'][0]['id'];
call('POST', '/messages/read', ['up_to_id' => $lastId], $tok['b']);
$r = call('GET', '/messages?limit=1', null, $tok['a']);
check('A cannot see read time when B disabled receipts', $r['json']['data']['messages'][0]['read_at'] === null, $r['json']['data']['messages'][0]);
call('PATCH', '/me', ['read_receipts' => true, 'last_seen_visibility' => 'nobody'], $tok['b']);
$r = call('GET', '/sync?since=0', null, $tok['a']);
check('last seen hidden when B chose nobody', $r['json']['data']['partner']['last_seen_at'] === null && $r['json']['data']['partner']['online'] === false, $r['json']['data']['partner']);
call('PATCH', '/me', ['last_seen_visibility' => 'partner'], $tok['b']);

echo "Media (encrypted at rest, private URLs)\n";
$img = imagecreatetruecolor(3000, 1500);
imagefill($img, 0, 0, imagecolorallocate($img, 220, 80, 120));
$png = tempnam(sys_get_temp_dir(), 'tst') . '.png';
imagepng($img, $png);
$r = call('POST', '/media', null, $tok['a'], [], ['kind' => 'image', 'file' => new CURLFile($png, 'image/png', 'heart.png')]);
check('upload image', $r['status'] === 201 && $r['json']['data']['mime'] === 'image/png', $r['json']);
$mediaId = $r['json']['data']['id'];
check('image downscaled to 2560px max', $r['json']['data']['width'] === 2560 && $r['json']['data']['height'] === 1280, $r['json']['data']);
$r = call('POST', '/messages', ['client_id' => uuid(), 'type' => 'image', 'media_id' => $mediaId], $tok['a']);
check('send image message', $r['status'] === 201 && $r['json']['data']['media']['id'] === $mediaId, $r['json']);
$r = call('GET', "/media/$mediaId", null, $tok['b']);
check('partner downloads image', $r['status'] === 200 && str_contains($r['headers'], 'Content-Type: image/png') && @imagecreatefromstring($r['raw']) !== false);
$r = call('GET', "/media/$mediaId", null, $tok['c']);
check('stranger gets 404 for media', $r['status'] === 404);
$r = call('GET', "/media/$mediaId");
check('no auth gets 401 for media', $r['status'] === 401);
$stored = glob(__DIR__ . '/../backend/storage/media/*/*.bin');
$anyPlain = false;
foreach ($stored as $f) {
    if (str_contains((string)file_get_contents($f, false, null, 0, 64), 'PNG')) {
        $anyPlain = true;
    }
}
check('files on disk are encrypted (no PNG header)', $stored && !$anyPlain);

$txt = tempnam(sys_get_temp_dir(), 'tst') . '.txt';
$content = '';
for ($i = 0; $i < 20000; $i++) {
    $content .= sprintf("%06d love\n", $i); // 240 KB, spans several chunks
}
file_put_contents($txt, $content);
$r = call('POST', '/media', null, $tok['b'], [], ['kind' => 'file', 'file' => new CURLFile($txt, 'text/plain', 'notes.txt')]);
check('upload file', $r['status'] === 201, $r['json']);
$fileId = $r['json']['data']['id'];
$r = call('GET', "/media/$fileId", null, $tok['a']);
check('full file round-trips exactly', $r['raw'] === $content);
$r = call('GET', "/media/$fileId", null, $tok['a'], ['Range: bytes=65530-131100']);
check('range request across chunk boundary', $r['status'] === 206 && $r['raw'] === substr($content, 65530, 131100 - 65530 + 1));
$r = call('GET', "/media/$fileId/link", null, $tok['a']);
$link = $r['json']['data']['url'];
$r = call('GET', parse_url($link, PHP_URL_PATH) . '?' . parse_url($link, PHP_URL_QUERY));
check('signed link works without header', $r['status'] === 200 && $r['raw'] === $content);
$r = call('GET', parse_url($link, PHP_URL_PATH) . '?' . str_replace('sig=', 'sig=0', parse_url($link, PHP_URL_QUERY)));
check('tampered signed link rejected', $r['status'] === 401);
$php = tempnam(sys_get_temp_dir(), 'tst') . '.php';
file_put_contents($php, '<?php echo 1;');
$r = call('POST', '/media', null, $tok['a'], [], ['kind' => 'file', 'file' => new CURLFile($php, 'image/png', 'evil.php')]);
check('PHP upload blocked', $r['status'] === 422, $r['json']);
$r = call('POST', '/messages', ['client_id' => uuid(), 'type' => 'image', 'media_id' => $fileId], $tok['a']);
check('cannot send partner-owned media as own', $r['status'] === 422, $r['json']);

echo "Disappearing messages\n";
call('PATCH', '/couple', ['disappearing_seconds' => 3600], $tok['a']);
$r = call('POST', '/messages', ['client_id' => uuid(), 'body' => 'this vanishes'], $tok['b']);
check('message gets expires_at', $r['json']['data']['expires_at'] !== null, $r['json']);
call('PATCH', '/couple', ['disappearing_seconds' => 0], $tok['a']);

echo "Sessions\n";
$r = call('GET', '/auth/sessions', null, $tok['a']);
check('A has 2 device sessions', count($r['json']['data']) === 2, $r['json']);
$other = array_values(array_filter($r['json']['data'], fn($s) => !$s['current']))[0]['id'];
call('DELETE', "/auth/sessions/$other", null, $tok['a']);
$r = call('GET', '/me', null, $tokA2);
check('revoked device is signed out', $r['status'] === 401);

echo "Brute-force protection\n";
$limited = false;
for ($i = 0; $i < 12; $i++) {
    $r = call('POST', '/couple/join', ['code' => sprintf('%06d', random_int(0, 999999))], $tok['c']);
    if ($r['status'] === 429) {
        $limited = true;
        break;
    }
}
check('pairing code guessing is rate limited', $limited);
for ($i = 0; $i < 6; $i++) {
    $r = call('POST', '/auth/login', ['email' => $emails['d'], 'password' => 'Nope12345']);
}
check('login locked after 5 failures', $r['status'] === 429, $r['json']);

echo "Unpair\n";
$r = call('POST', '/couple/unpair', ['password' => 'wrong'], $tok['b']);
check('unpair needs password', $r['status'] === 403);
$r = call('POST', '/couple/unpair', ['password' => $pw, 'block' => true], $tok['b']);
check('B unpairs and blocks', $r['status'] === 200 && $r['json']['data']['blocked'] === true, $r['json']);
$r = call('GET', '/messages', null, $tok['a']);
check('A loses chat access after unpair', $r['status'] === 409);
$r = call('GET', "/media/$mediaId", null, $tok['b']);
check('B loses access to A media after unpair', $r['status'] === 404);
$r = call('POST', '/couple/invite', null, $tok['a']);
$code = $r['json']['data']['code'];
$r = call('POST', '/couple/join', ['code' => $code], $tok['b']);
check('blocked pair cannot reconnect', $r['status'] === 404, $r['json']);

echo "Password reset\n";
$r = call('POST', '/auth/forgot-password', ['email' => "nobody.$run@example.com"]);
check('unknown email gets same answer', $r['status'] === 200 && $r['json']['data']['sent'] === true);
call('POST', '/auth/forgot-password', ['email' => $emails['a']]);
$code = lastCode($emails['a']);
$r = call('POST', '/auth/reset-password', ['email' => $emails['a'], 'code' => $code, 'password' => 'NewSunrise2027']);
check('reset password', $r['status'] === 200, $r['json']);
$r = call('GET', '/me', null, $tok['a']);
check('old sessions revoked after reset', $r['status'] === 401);
$r = call('POST', '/auth/reset-password', ['email' => $emails['a'], 'code' => $code, 'password' => 'Another2028']);
check('reset code is single-use', $r['status'] === 400);
$r = call('POST', '/auth/login', ['email' => $emails['a'], 'password' => 'NewSunrise2027']);
check('login with new password', $r['status'] === 200);

echo "Export and deletion\n";
$r = call('GET', '/me/export', null, $tok['b']);
check('data export includes sent messages', $r['status'] === 200 && count($r['json']['data']['messages_sent']) === 2, $r['json']['data']['messages_sent'] ?? $r['json']);
$r = call('POST', '/me/delete', ['password' => $pw], $tok['c']);
check('account deletion scheduled', $r['json']['data']['deletion_scheduled'] === true, $r['json']);
$r = call('GET', '/me', null, $tok['c']);
check('deleted account signed out', $r['status'] === 401);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
