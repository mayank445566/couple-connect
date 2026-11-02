<?php
declare(strict_types=1);
/**
 * Phase 6 end-to-end test: Google Play Billing (server verification, account binding, pending,
 * acknowledgement, real-time notifications for completed and voided purchases) and API security headers.
 * Starts a local stand-in for Google (tests/mock_google.php) that checks the service-account JWT signature.
 * Server .env (APP_ENV=local) needs: PLAY_PACKAGE_NAME, PLAY_SERVICE_ACCOUNT (path this test writes),
 * PLAY_RTDN_TOKEN, GOOGLE_TEST_BASE=http://127.0.0.1:8097.
 * Usage: API=http://127.0.0.1:8088 php tests/phase6_test.php
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/../backend/src/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$run = bin2hex(random_bytes(3));
$pkg = Config::require('PLAY_PACKAGE_NAME');
$rtdn = Config::require('PLAY_RTDN_TOKEN');
$tmp = sys_get_temp_dir() . "/p6_$run";
mkdir($tmp);

// Service account with a fresh RSA key; the mock verifies JWTs with the public half
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $pem);
file_put_contents("$tmp/pub.pem", openssl_pkey_get_details($key)['key']);
file_put_contents(Config::require('PLAY_SERVICE_ACCOUNT'), json_encode(['type' => 'service_account', 'client_email' => "play-$run@test.iam.gserviceaccount.com", 'private_key' => $pem]));
foreach (glob(Config::storage('cache') . '/gtoken_*.json') as $f) {
    unlink($f);
}
$STATE = "$tmp/state.json";
file_put_contents($STATE, json_encode(['package' => $pkg, 'purchases' => [], 'log' => []]));
$mock = proc_open(['php', '-S', '127.0.0.1:8097', __DIR__ . '/mock_google.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['MOCK_STATE' => $STATE, 'MOCK_PUBKEY' => "$tmp/pub.pem", 'PATH' => getenv('PATH')]);
usleep(400000);

function purchase(string $token, array $p): void
{
    global $STATE;
    $s = json_decode(file_get_contents($STATE), true);
    $s['purchases'][$token] = $p + ['productId' => 'premium_lifetime', 'acknowledgementState' => 0, 'consumptionState' => 0, 'purchaseType' => null];
    file_put_contents($STATE, json_encode($s));
}
function mockLog(): array
{
    global $STATE;
    return json_decode(file_get_contents($STATE), true)['log'];
}
function rtdn(array $data, ?string $token, string $id): array
{
    global $pkg;
    $body = ['message' => ['data' => base64_encode(json_encode(['version' => '1.0', 'packageName' => $pkg, 'eventTimeMillis' => (string)(time() * 1000)] + $data)), 'messageId' => $id], 'subscription' => 'projects/x/subscriptions/play'];
    return call('POST', '/webhooks/google-play' . ($token !== null ? '?token=' . urlencode($token) : ''), $body);
}
function premium(string $tok): bool
{
    return call('POST', '/store/restore', null, $tok)['json']['data']['entitlements']['premium']['owned'] === true;
}

[$A, $aId] = makeUser("p6a.$run@example.com", 'Isha');
[$B, $bId] = makeUser("p6b.$run@example.com", 'Dev');
[$C, $cId] = makeUser("p6c.$run@example.com", 'Kunal');
pair($A, $B);

echo "Play Billing setup\n";
$cfg = call('GET', '/store/google/config', null, $A)['json']['data'];
check('config: enabled, product mapped, account tag issued', $cfg['enabled'] === true && $cfg['products'][0]['play_product_id'] === 'premium_lifetime' && str_starts_with($cfg['account_tag'], "u$aId."), $cfg);
$tagA = $cfg['account_tag'];
$tagC = call('GET', '/store/google/config', null, $C)['json']['data']['account_tag'];
check('account tags differ per user and cannot be guessed', $tagA !== $tagC && !str_contains($tagA, (string)Config::get('APP_KEY')));

echo "Verification\n";
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "nope-$run"], $A);
check('unknown token rejected', $r['status'] === 400 && $r['json']['error']['code'] === 'invalid_purchase', $r['json']);
check('service-account JWT accepted by Google stand-in', in_array('token:https://www.googleapis.com/auth/androidpublisher', mockLog(), true));
purchase("tokC-$run", ['purchaseState' => 0, 'orderId' => "GPA.C-$run", 'obfuscatedExternalAccountId' => $tagC]);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokC-$run"], $A);
check("someone else's purchase token cannot be redeemed", $r['status'] === 403 && !premium($A), $r['json']);
purchase("tokP-$run", ['purchaseState' => 2, 'orderId' => "GPA.P-$run", 'obfuscatedExternalAccountId' => $tagA]);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokP-$run"], $A);
check('pending payment does not unlock', $r['json']['data']['pending'] === true && !premium($A), $r['json']);
purchase("tokX-$run", ['purchaseState' => 1, 'orderId' => "GPA.X-$run", 'obfuscatedExternalAccountId' => $tagA]);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokX-$run"], $A);
check('cancelled purchase rejected', $r['status'] === 402);
purchase("tokA-$run", ['purchaseState' => 0, 'orderId' => "GPA.A-$run", 'obfuscatedExternalAccountId' => $tagA]);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokA-$run"], $A);
check('valid purchase unlocks Premium', $r['status'] === 200 && $r['json']['data']['paid'] === true && premium($A), $r['json']);
check('partner shares Premium', premium($B));
check('purchase acknowledged with Google (no auto-refund after 3 days)', in_array("ack:tokA-$run", mockLog(), true));
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokA-$run"], $A);
check('verifying again is harmless', $r['status'] === 200 && (int)Database::value("SELECT COUNT(*) FROM orders WHERE gateway = 'google' AND gateway_order_id = ?", ["GPA.A-$run"]) === 1);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokA-$run"], $C);
check('same token from another account refused', $r['status'] === 403 && !premium($C));
check('access token cached (one token request for many calls)', count(array_filter(mockLog(), fn($l) => str_starts_with($l, 'token:'))) === 1);
$hist = call('GET', '/store/purchases', null, $A)['json']['data'];
check('purchase appears in history', (bool)array_filter($hist, fn($h) => $h['payment_id'] === "GPA.A-$run" && $h['status'] === 'paid'));

echo "Real-time developer notifications\n";
$r = rtdn(['voidedPurchaseNotification' => ['purchaseToken' => "tokA-$run", 'orderId' => "GPA.A-$run", 'productType' => 2]], null, "m0-$run");
check('notification without the secret token rejected', $r['status'] === 401);
$r = rtdn(['voidedPurchaseNotification' => ['purchaseToken' => "tokA-$run", 'orderId' => "GPA.A-$run", 'productType' => 2, 'refundType' => 1]], 'wrong', "m0-$run");
check('notification with wrong token rejected', $r['status'] === 401);
$r = rtdn(['voidedPurchaseNotification' => ['purchaseToken' => "tokA-$run", 'orderId' => "GPA.A-$run", 'productType' => 2, 'refundType' => 1]], $rtdn, "m1-$run");
check('refund in Play removes Premium for both partners', $r['status'] === 200 && !premium($A) && !premium($B) && Database::value("SELECT status FROM orders WHERE gateway_order_id = ?", ["GPA.A-$run"]) === 'refunded', $r['json']);
$r = rtdn(['voidedPurchaseNotification' => ['purchaseToken' => "tokA-$run", 'orderId' => "GPA.A-$run"]], $rtdn, "m1-$run");
check('duplicate notification ignored', ($r['json']['data']['duplicate'] ?? false) === true);
$r = call('POST', '/store/google/verify', ['sku' => 'premium_lifetime', 'purchase_token' => "tokA-$run"], $A);
check('refunded purchase cannot be re-applied', !premium($A));
purchase("tokP-$run", ['purchaseState' => 0, 'orderId' => "GPA.P-$run", 'obfuscatedExternalAccountId' => $tagA]);
$r = rtdn(['oneTimeProductNotification' => ['version' => '1.0', 'notificationType' => 1, 'purchaseToken' => "tokP-$run", 'sku' => 'premium_lifetime']], $rtdn, "m2-$run");
check('pending payment completed later unlocks via notification', $r['status'] === 200 && premium($A), $r['json']);
$r = rtdn(['testNotification' => ['version' => '1.0']], $rtdn, "m3-$run");
check('Play Console test notification accepted', $r['status'] === 200);

echo "API security headers\n";
$r = call('GET', '/health');
check('API sends nosniff, no-referrer, frame deny', str_contains($r['headers'], 'X-Content-Type-Options: nosniff') && str_contains($r['headers'], 'Referrer-Policy: no-referrer') && str_contains($r['headers'], 'X-Frame-Options: DENY'));
check('API responses are not cached', str_contains(call('GET', '/me', null, $A)['headers'], 'Cache-Control: no-store'));
$r = call('GET', '/install.php');
check('installer refuses to run on an installed server', $r['status'] === 403 || $r['status'] === 404);
$r = call('GET', '/../.env');
check('.env is not reachable over HTTP', !str_contains($r['raw'], 'APP_KEY'));

proc_terminate($mock);
array_map('unlink', glob("$tmp/*"));
rmdir($tmp);
finish();
