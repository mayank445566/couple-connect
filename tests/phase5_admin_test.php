<?php
declare(strict_types=1);
/**
 * Phase 5 end-to-end test: admin panel sign-in, two-step codes, CSRF, roles, report moderation,
 * refunds, content editing, settings, audited case access and the audit log.
 * Runs on the same machine as the API (reads backend/.env and the database to fetch 2FA secrets,
 * and to simulate time passing for one-time codes and idle sessions).
 * Usage: API=http://127.0.0.1:8088 php tests/phase5_admin_test.php
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/../backend/src/bootstrap.php';

use App\Admin\Totp;
use App\Core\Database;

$run = bin2hex(random_bytes(3));
$JARS = [];

/** Browser-like request with a cookie jar per admin. */
function web(string $who, string $method, string $path, ?array $form = null, array $headers = []): array
{
    global $API, $JARS;
    $JARS[$who] ??= tempnam(sys_get_temp_dir(), "jar_$who");
    $ch = curl_init($API . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $JARS[$who],
        CURLOPT_COOKIEFILE => $JARS[$who],
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = (string)curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    return ['status' => $status, 'body' => substr($raw, $hs), 'headers' => $head, 'location' => isset($m[1]) ? (string)parse_url($m[1], PHP_URL_PATH) : null];
}

function csrfOf(string $html): string
{
    preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m);
    return $m[1] ?? '';
}

/** POST a form with the CSRF token from the page at $from. */
function submit(string $who, string $from, string $to, array $form): array
{
    $page = web($who, 'GET', $from);
    return web($who, 'POST', $to, $form + ['_csrf' => csrfOf($page['body'])]);
}

/** Code for the next unused 30 s step. Clears the replay marker to simulate time passing between steps in a fast test. */
function code(string $email): string
{
    $a = Database::one('SELECT totp_secret FROM admin_users WHERE email = ?', [$email]);
    Database::run('UPDATE admin_users SET totp_last_step = NULL WHERE email = ?', [$email]);
    return Totp::code(Totp::unbase32(Totp::open($a['totp_secret'])), intdiv(time(), 30));
}

function login(string $who, string $email, string $password): array
{
    $p = web($who, 'GET', '/admin/login');
    preg_match('/name="_pre" value="([a-f0-9]+)"/', $p['body'], $m);
    $r = web($who, 'POST', '/admin/login', ['_pre' => $m[1] ?? '', 'email' => $email, 'password' => $password]);
    if ($r['location'] === '/admin/2fa') {
        $p = web($who, 'GET', '/admin/2fa');
        preg_match('/name="_pre" value="([a-f0-9]+)"/', $p['body'], $m);
        $r = web($who, 'POST', '/admin/2fa', ['_pre' => $m[1] ?? '', 'code' => code($email)]);
    }
    return $r;
}

/** First sign-in of a new admin: temporary password → own password → two-step sign-in. */
function onboard(string $who, string $email, string $temp): void
{
    login($who, $email, $temp);
    submit($who, '/admin/account', '/admin/account/password', ['current' => $temp, 'new' => 'Admin-Pass-2026x', 'confirm' => 'Admin-Pass-2026x']);
    submit($who, '/admin/account', '/admin/account/2fa/start', []);
    submit($who, '/admin/account', '/admin/account/2fa/confirm', ['code' => code($email)]);
}

function tempFrom(string $html): string
{
    preg_match('/Temporary password for <b>[^<]+<\/b>: <code>([^<]+)<\/code>/', $html, $m);
    return $m[1] ?? '';
}

// ---------------------------------------------------------------- setup
$owner = "owner.$run@example.com";
$out = shell_exec('php ' . escapeshellarg(__DIR__ . '/../backend/bin/admin.php') . ' create ' . escapeshellarg($owner) . ' "Owner ' . $run . '" owner');
preg_match('/Temporary password: (\S+)/', (string)$out, $m);
$ownerTemp = $m[1] ?? '';

[$A, $aId] = makeUser("p5a.$run@example.com", 'Riya');
[$B, $bId] = makeUser("p5b.$run@example.com", 'Arjun');
pair($A, $B);
call('POST', '/messages', ['client_id' => uuid(), 'body' => "harmless hello $run"], $A);
$bad = call('POST', '/messages', ['client_id' => uuid(), 'body' => "threatening message $run"], $B)['json']['data']['id'];
$img = imagecreatetruecolor(40, 40);
$png = tempnam(sys_get_temp_dir(), 'p5') . '.png';
imagepng($img, $png);
$media = call('POST', '/media', null, $B, [], ['kind' => 'image', 'file' => new CURLFile($png, 'image/png', 'x.png')])['json']['data']['id'];
$imgMsg = call('POST', '/messages', ['client_id' => uuid(), 'type' => 'image', 'media_id' => $media], $B)['json']['data']['id'];
$private = call('POST', '/media', null, $B, [], ['kind' => 'image', 'file' => new CURLFile($png, 'image/png', 'y.png')])['json']['data']['id'];
call('POST', '/messages', ['client_id' => uuid(), 'type' => 'image', 'media_id' => $private, 'body' => "private photo caption $run"], $B);
$report = call('POST', '/reports', ['reason' => 'harassment', 'details' => 'He keeps threatening me', 'reported_user_id' => $bId, 'message_ids' => [$bad, $imgMsg]], $A)['json']['data']['report_id'];
$coupleId = (int)Database::value('SELECT active_couple_id FROM users WHERE id = ?', [$aId]);

echo "Sign-in and session security\n";
$r = web('owner', 'GET', '/admin/login');
check('login page served with strict CSP and no framing', $r['status'] === 200 && str_contains($r['headers'], "frame-ancestors 'none'") && str_contains($r['headers'], 'X-Frame-Options: DENY') && str_contains($r['headers'], 'no-store'));
$r = web('owner', 'GET', '/admin');
check('panel requires sign-in', $r['status'] === 303 && $r['location'] === '/admin/login');
$r = web('anon', 'POST', '/admin/login', ['email' => $owner, 'password' => $ownerTemp]);
check('login form without its token is refused', $r['status'] === 419);
$r = login('owner', $owner, 'wrong-password-1');
check('wrong password rejected', $r['status'] === 401 && str_contains($r['body'], 'incorrect'));
check('failed login written to audit log', (bool)Database::value("SELECT 1 FROM admin_audit_log WHERE action = 'login_failed' AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.email')) = ?", [$owner]));
$r = login('owner', $owner, $ownerTemp);
check('temporary password signs in', $r['status'] === 303 && $r['location'] === '/admin');
$r = web('owner', 'GET', '/admin/users');
check('temporary password must be changed first', $r['location'] === '/admin/account');
$r = submit('owner', '/admin/account', '/admin/account/password', ['current' => $ownerTemp, 'new' => 'short1', 'confirm' => 'short1']);
check('weak admin password rejected', str_contains(web('owner', 'GET', '/admin/account')['body'], '12 characters'));
submit('owner', '/admin/account', '/admin/account/password', ['current' => $ownerTemp, 'new' => 'Owner-Pass-2026x', 'confirm' => 'Owner-Pass-2026x']);
$r = web('owner', 'GET', '/admin/users');
check('two-step sign-in required before using the panel', $r['location'] === '/admin/account');
submit('owner', '/admin/account', '/admin/account/2fa/start', []);
$acct = web('owner', 'GET', '/admin/account')['body'];
check('setup key shown for authenticator app', str_contains($acct, 'otpauth://totp/'));
$r = submit('owner', '/admin/account', '/admin/account/2fa/confirm', ['code' => '000000']);
check('wrong setup code rejected', str_contains(web('owner', 'GET', '/admin/account')['body'], 'not valid'));
$r = submit('owner', '/admin/account', '/admin/account/2fa/confirm', ['code' => code($owner)]);
check('two-step sign-in turned on', $r['location'] === '/admin' && Database::value('SELECT totp_enabled_at FROM admin_users WHERE email = ?', [$owner]) !== null);
check('2FA secret stored encrypted', !str_contains((string)Database::value('SELECT totp_secret FROM admin_users WHERE email = ?', [$owner]), Totp::open((string)Database::value('SELECT totp_secret FROM admin_users WHERE email = ?', [$owner]))));
$r = web('owner', 'GET', '/admin');
check('dashboard opens with counts only', $r['status'] === 200 && str_contains($r['body'], 'Active couples') && !str_contains($r['body'], "threatening message $run"));
$r = web('owner', 'POST', '/admin/settings', ['adult_section_enabled' => '1']);
check('POST without CSRF token refused', $r['status'] === 419);
$r = web('owner', 'POST', '/admin/settings', ['_csrf' => csrfOf(web('owner', 'GET', '/admin/settings')['body'])], ['Origin: https://evil.example']);
check('cross-site POST refused', $r['status'] === 403);
submit('owner', '/admin', '/admin/logout', []);
check('sign out ends the session', web('owner', 'GET', '/admin')['location'] === '/admin/login');
$p = web('owner', 'GET', '/admin/login');
preg_match('/name="_pre" value="([a-f0-9]+)"/', $p['body'], $m);
$r = web('owner', 'POST', '/admin/login', ['_pre' => $m[1], 'email' => $owner, 'password' => 'Owner-Pass-2026x']);
check('second sign-in asks for a code', $r['location'] === '/admin/2fa');
check('code step is not a usable session', web('owner', 'GET', '/admin')['location'] === '/admin/login');
$p = web('owner', 'GET', '/admin/2fa');
preg_match('/name="_pre" value="([a-f0-9]+)"/', $p['body'], $m);
$r = web('owner', 'POST', '/admin/2fa', ['_pre' => $m[1], 'code' => '123456']);
check('wrong code rejected', $r['status'] === 401);
$good = code($owner);
$r = web('owner', 'POST', '/admin/2fa', ['_pre' => $m[1], 'code' => $good]);
check('right code opens the panel', $r['location'] === '/admin' && web('owner', 'GET', '/admin')['status'] === 200);
check('used code is remembered (replay blocked)', Totp::verify(Totp::open((string)Database::value('SELECT totp_secret FROM admin_users WHERE email = ?', [$owner])), $good, (int)Database::value('SELECT totp_last_step FROM admin_users WHERE email = ?', [$owner])) === null);

echo "Roles\n";
$mk = function (string $email, string $role) {
    $r = submit('owner', '/admin/admins', '/admin/admins', ['email' => $email, 'name' => ucfirst($role), 'role' => $role]);
    return tempFrom($r['body']);
};
$mod = "mod.$run@example.com";
$fin = "fin.$run@example.com";
$con = "con.$run@example.com";
$modTemp = $mk($mod, 'moderator');
check('owner creates admins with a one-time temporary password', strlen($modTemp) >= 16);
onboard('mod', $mod, $modTemp);
onboard('fin', $fin, $mk($fin, 'finance'));
onboard('con', $con, $mk($con, 'content'));
check('moderator reaches reports', web('mod', 'GET', '/admin/reports')['status'] === 200);
check('moderator cannot open orders', web('mod', 'GET', '/admin/orders')['status'] === 403);
check('moderator cannot use case access', web('mod', 'GET', '/admin/cases')['status'] === 403);
check('finance cannot open reports', web('fin', 'GET', '/admin/reports')['status'] === 403);
check('content cannot open users', web('con', 'GET', '/admin/users')['status'] === 403);
check('only owners see the audit log', web('mod', 'GET', '/admin/audit')['status'] === 403 && web('owner', 'GET', '/admin/audit')['status'] === 200);
$r = submit('owner', '/admin/admins', '/admin/admins/' . (int)Database::value('SELECT id FROM admin_users WHERE email = ?', [$owner]), ['op' => 'disable']);
check('cannot disable yourself', str_contains(web('owner', 'GET', '/admin/admins')['body'], 'cannot change your own'));

echo "Users and reports\n";
$r = web('mod', 'GET', "/admin/users/$bId");
check('user page shows account facts but no chat text', $r['status'] === 200 && str_contains($r['body'], 'Arjun') && !str_contains($r['body'], "harmless hello $run") && !str_contains($r['body'], "threatening message $run"));
$r = web('mod', 'GET', "/admin/reports/$report");
check('report shows the attached messages', str_contains($r['body'], "threatening message $run"));
check('report does not show messages that were not attached', !str_contains($r['body'], "harmless hello $run") && !str_contains($r['body'], "private photo caption $run"));
$r = web('mod', 'GET', "/admin/reports/$report/media/$media");
check('attached photo opens for the moderator', $r['status'] === 200 && str_contains($r['headers'], 'Content-Type: image/png') && str_contains($r['headers'], 'sandbox'));
$r = web('mod', 'GET', "/admin/reports/$report/media/$private");
check('a photo not attached to the report is refused', $r['status'] === 404);
$r = submit('mod', "/admin/reports/$report", "/admin/reports/$report", ['status' => 'resolved', 'action' => 'suspended', 'note' => '']);
check('decision needs a note', str_contains(web('mod', 'GET', "/admin/reports/$report")['body'], 'short note'));
$r = submit('mod', "/admin/reports/$report", "/admin/reports/$report", ['status' => 'resolved', 'action' => 'suspended', 'note' => 'Threats confirmed in attached messages.']);
check('report resolved with suspension', Database::value('SELECT status FROM reports WHERE id = ?', [$report]) === 'resolved' && Database::value('SELECT status FROM users WHERE id = ?', [$bId]) === 'suspended');
$r = call('GET', '/me', null, $B);
check('suspended user is signed out of the app', $r['status'] === 401 || $r['status'] === 403);
$r = submit('mod', "/admin/users/$bId", "/admin/users/$bId/status", ['action' => 'reactivate', 'reason' => '']);
check('moderation needs a reason', Database::value('SELECT status FROM users WHERE id = ?', [$bId]) === 'suspended');
submit('mod', "/admin/users/$bId", "/admin/users/$bId/status", ['action' => 'reactivate', 'reason' => 'Appeal accepted']);
check('account reactivated', Database::value('SELECT status FROM users WHERE id = ?', [$bId]) === 'active');
$B = call('POST', '/auth/login', ['email' => "p5b.$run@example.com", 'password' => 'Sunrise2026'])['json']['data']['token'] ?? $B;

echo "Payments and refunds\n";
$o = call('POST', '/store/orders', ['sku' => 'premium_lifetime'], $A)['json']['data'];
$gw = $o['checkout']['order_id'];
$pay = 'pay_' . bin2hex(random_bytes(7));
call('POST', "/store/orders/{$o['order_id']}/verify", ['razorpay_order_id' => $gw, 'razorpay_payment_id' => $pay, 'razorpay_signature' => hash_hmac('sha256', "$gw|$pay", (string)\App\Core\Config::get('RAZORPAY_KEY_SECRET'))], $A);
check('premium bought', call('GET', '/store/products', null, $A)['json']['data']['products'][0]['owned'] === true);
$r = web('fin', 'GET', "/admin/orders/{$o['order_id']}");
check('finance sees the order and payment, no secrets', $r['status'] === 200 && str_contains($r['body'], $pay) && !str_contains($r['body'], (string)\App\Core\Config::get('RAZORPAY_KEY_SECRET')));
$r = submit('fin', "/admin/orders/{$o['order_id']}", "/admin/orders/{$o['order_id']}/refund", ['reason' => 'Asked within 7 days']);
check('refund needs the confirmation box', Database::value('SELECT status FROM orders WHERE id = ?', [$o['order_id']]) === 'paid');
$r = submit('fin', "/admin/orders/{$o['order_id']}", "/admin/orders/{$o['order_id']}/refund", ['reason' => 'Asked within 7 days', 'confirm' => '1']);
$ord = Database::one('SELECT * FROM orders WHERE id = ?', [$o['order_id']]);
check('order refunded with gateway refund id', $ord['status'] === 'refunded' && str_starts_with((string)$ord['refund_gateway_id'], 'rfnd_') && $ord['refunded_at'] !== null);
check('premium removed for buyer and partner', !call('GET', '/store/products', null, $A)['json']['data']['products'][0]['owned'] && !call('GET', '/store/products', null, $B)['json']['data']['products'][0]['owned']);
$r = submit('fin', "/admin/orders/{$o['order_id']}", "/admin/orders/{$o['order_id']}/refund", ['reason' => 'again', 'confirm' => '1']);
check('second refund refused', (int)Database::value("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'order_refunded' AND target_id = ?", [$o['order_id']]) === 1);
submit('fin', "/admin/users/$aId", "/admin/users/$aId/entitlements", ['op' => 'grant', 'entitlement' => 'premium', 'reason' => 'Goodwill after refund issue']);
check('manual premium grant works and is audited', call('POST', '/store/restore', null, $A)['json']['data']['entitlements']['premium']['owned'] === true && (bool)Database::value("SELECT 1 FROM admin_audit_log WHERE action = 'entitlement_grant' AND target_id = ?", [$aId]));

echo "Content\n";
$gid = (int)Database::value("SELECT id FROM games WHERE slug = 'would-you-rather'");
$g = Database::one('SELECT * FROM games WHERE id = ?', [$gid]);
submit('con', "/admin/games/$gid", "/admin/games/$gid", ['title' => "WYR $run", 'description' => $g['description'], 'rounds' => (string)$g['rounds'], 'sort_order' => (string)$g['sort_order'], 'enabled' => '1']);
$titles = array_column(call('GET', '/games', null, $A)['json']['data']['games']['free'], 'title');
check('game edit shows in the app', in_array("WYR $run", $titles, true), $titles);
submit('con', "/admin/games/$gid", "/admin/games/$gid", ['title' => $g['title'], 'description' => $g['description'], 'rounds' => (string)$g['rounds'], 'sort_order' => (string)$g['sort_order'], 'enabled' => '1']);
$pid = (int)Database::value('SELECT id FROM question_packs WHERE game_id = ? AND couple_id IS NULL ORDER BY id LIMIT 1', [$gid]);
$before = (int)Database::value('SELECT COUNT(*) FROM questions WHERE pack_id = ?', [$pid]);
submit('con', "/admin/packs/$pid", "/admin/packs/$pid/questions", ['kind' => 'question', 'text' => "Mountains or beaches? $run", 'options' => '["Mountains","Beaches"]']);
check('question added', (int)Database::value('SELECT COUNT(*) FROM questions WHERE pack_id = ?', [$pid]) === $before + 1);
submit('con', "/admin/packs/$pid", "/admin/packs/$pid/questions", ['kind' => 'question', 'text' => 'bad options', 'options' => '{"a":1}']);
check('invalid options rejected', (int)Database::value('SELECT COUNT(*) FROM questions WHERE pack_id = ?', [$pid]) === $before + 1);
call('POST', '/games/would-you-rather/custom-questions', ['text' => "Our secret question $run", 'options' => ['x', 'y']], $A);
$custom = (int)Database::value('SELECT id FROM question_packs WHERE couple_id = ?', [$coupleId]);
check("couple's own pack is private from admins", $custom > 0 && web('con', 'GET', "/admin/packs/$custom")['status'] === 404 && !str_contains(web('con', 'GET', "/admin/games/$gid")['body'], "Our secret question $run"));
$r = submit('con', '/admin/themes/new', '/admin/themes/new', ['name' => 'Bad', 'tokens' => '{"primary":"red;background:url(x)"}']);
check('unsafe theme colours rejected', !Database::value("SELECT 1 FROM themes WHERE name = 'Bad'"));
$tokens = json_encode(['primary' => '#112233', 'background' => '#FFFFFF', 'surface' => '#F0F0F0', 'text' => '#111111', 'bubble_mine' => '#112233', 'bubble_theirs' => 'rgba(0,0,0,0.1)', 'dark' => false]);
submit('con', '/admin/themes/new', '/admin/themes/new', ['name' => "Test $run", 'slug' => "test-$run", 'tokens' => $tokens, 'is_premium' => '1', 'enabled' => '1']);
check('new theme created', (bool)Database::value('SELECT 1 FROM themes WHERE slug = ?', ["test-$run"]));
Database::run('DELETE FROM themes WHERE slug = ?', ["test-$run"]);

echo "Settings and pricing\n";
submit('owner', '/admin/settings', '/admin/settings', ['premium_shared_with_partner' => '1', 'themes_in_premium' => '1', 'daily_question_pack' => \App\Services\Settings::get('daily_question_pack')]);
$r = call('GET', '/games', null, $A);
check('turning off the 18+ section takes effect in the app', Database::value("SELECT v FROM settings WHERE k = 'adult_section_enabled'") === '0' && $r['json']['data']['adult']['available'] === false, $r['json']['data']['adult'] ?? null);
submit('owner', '/admin/settings', '/admin/settings', ['adult_section_enabled' => '1', 'premium_shared_with_partner' => '1', 'themes_in_premium' => '1', 'daily_question_pack' => \App\Services\Settings::get('daily_question_pack')]);
check('settings change audited', (int)Database::value("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'settings_updated' AND admin_email = ?", [$owner]) >= 2);
check('health check never prints secrets', !str_contains(web('owner', 'GET', '/admin/settings')['body'], (string)\App\Core\Config::get('RAZORPAY_KEY_SECRET')));
$prod = (int)Database::value("SELECT id FROM products WHERE sku = 'premium_lifetime'");
submit('owner', '/admin/products', "/admin/products/$prod", ['title' => 'Couple Connect Premium', 'description' => 'x', 'price' => '0', 'active' => '1']);
check('price below ₹1 rejected', (int)Database::value('SELECT price_paise FROM products WHERE id = ?', [$prod]) === 19900);
check('finance cannot change prices', web('fin', 'GET', '/admin/products')['status'] === 403);

echo "Audited case access\n";
$r = web('owner', 'GET', "/admin/cases?couple=$coupleId");
check('case form explains logging', $r['status'] === 200 && str_contains($r['body'], 'audit log'));
$r = submit('owner', "/admin/cases?couple=$coupleId", '/admin/cases', ['couple_id' => $coupleId, 'basis' => 'suspected_illegal', 'reference' => 'T-1', 'reason' => 'too short', 'code' => code($owner)]);
check('case needs a real reason', !Database::value('SELECT 1 FROM case_access_grants WHERE couple_id = ?', [$coupleId]));
$r = submit('owner', "/admin/cases?couple=$coupleId", '/admin/cases', ['couple_id' => $coupleId, 'basis' => 'suspected_illegal', 'reference' => 'T-1', 'reason' => 'Suspected threats, following up report review.', 'code' => '000000']);
check('case needs a valid two-step code', !Database::value('SELECT 1 FROM case_access_grants WHERE couple_id = ?', [$coupleId]));
$r = submit('owner', "/admin/cases?couple=$coupleId", '/admin/cases', ['couple_id' => $coupleId, 'basis' => 'user_report', 'reference' => '999999999', 'reason' => 'Suspected threats, following up report review.', 'code' => code($owner)]);
check('user-report basis must name a real report for this couple', !Database::value('SELECT 1 FROM case_access_grants WHERE couple_id = ?', [$coupleId]));
$r = submit('owner', "/admin/cases?couple=$coupleId", '/admin/cases', ['couple_id' => $coupleId, 'basis' => 'suspected_illegal', 'reference' => 'T-1', 'reason' => 'Suspected threats, following up report review.', 'code' => code($owner)]);
$case = (int)Database::value('SELECT id FROM case_access_grants WHERE couple_id = ? ORDER BY id DESC LIMIT 1', [$coupleId]);
check('case opened for 24 hours', $case > 0 && $r['location'] === "/admin/cases/$case" && (int)Database::value('SELECT TIMESTAMPDIFF(HOUR, created_at, expires_at) FROM case_access_grants WHERE id = ?', [$case]) === 24);
$r = web('owner', 'GET', "/admin/cases/$case");
check('case shows the whole chat of that couple', str_contains($r['body'], "harmless hello $run") && str_contains($r['body'], "private photo caption $run"));
check('viewing is logged', (bool)Database::value("SELECT 1 FROM admin_audit_log WHERE action = 'case_messages_viewed' AND target_id = ?", [$coupleId]));
$r = web('owner', 'GET', "/admin/cases/$case/media/$private");
check('photo in the case opens and is logged', $r['status'] === 200 && (bool)Database::value("SELECT 1 FROM admin_audit_log WHERE action = 'case_media_viewed' AND JSON_EXTRACT(details, '$.media') = ?", [$private]));
[$X] = makeUser("p5x.$run@example.com", 'Other');
[$Y] = makeUser("p5y.$run@example.com", 'Couple');
pair($X, $Y);
$otherMedia = call('POST', '/media', null, $X, [], ['kind' => 'image', 'file' => new CURLFile($png, 'image/png', 'z.png')])['json']['data']['id'];
check("another couple's photo is not reachable through this case", web('owner', 'GET', "/admin/cases/$case/media/$otherMedia")['status'] === 404);
$owner2 = "owner2.$run@example.com";
onboard('owner2', $owner2, $mk($owner2, 'owner'));
check("another owner cannot use someone else's case", web('owner2', 'GET', "/admin/cases/$case")['status'] === 403);
check('other owners can see that the case exists', str_contains(web('owner2', 'GET', '/admin/cases')['body'], 'Suspected threats'));
$r = call('DELETE', "/messages/$bad?scope=everyone", null, $B);
check('sender deletes for everyone', $r['status'] === 200, $r['json']);
check('deleted messages stay deleted in a case', !str_contains(web('owner', 'GET', "/admin/cases/$case")['body'], "threatening message $run"));
submit('owner', "/admin/cases/$case", "/admin/cases/$case/close", []);
check('closed case cannot be viewed', web('owner', 'GET', "/admin/cases/$case")['status'] === 403);
Database::run('UPDATE case_access_grants SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND, closed_at = NULL WHERE id = ?', [$case]);
check('expired case cannot be viewed', web('owner', 'GET', "/admin/cases/$case")['status'] === 403);

echo "Audit log and sessions\n";
$r = web('owner', 'GET', '/admin/audit?action=case_');
check('audit log lists case access with reason', str_contains($r['body'], 'case_access_opened') && str_contains($r['body'], 'Suspected threats'));
Database::run('UPDATE admin_sessions SET last_seen_at = UTC_TIMESTAMP() - INTERVAL 31 MINUTE WHERE admin_id = (SELECT id FROM admin_users WHERE email = ?)', [$mod]);
check('idle session expires after 30 minutes', web('mod', 'GET', '/admin/reports')['location'] === '/admin/login');
$fid = (int)Database::value('SELECT id FROM admin_users WHERE email = ?', [$fin]);
submit('owner', '/admin/admins', "/admin/admins/$fid", ['op' => 'disable']);
check('disabled admin is signed out at once', web('fin', 'GET', '/admin/orders')['location'] === '/admin/login');

// Clean up admin accounts made by this run
Database::run('DELETE g FROM case_access_grants g JOIN admin_users a ON a.id = g.admin_id WHERE a.email IN (?, ?)', [$owner, $owner2]);
Database::run('DELETE FROM admin_users WHERE email IN (?, ?, ?, ?, ?)', [$owner, $owner2, $mod, $fin, $con]);
foreach ($JARS as $j) {
    @unlink($j);
}
finish();
