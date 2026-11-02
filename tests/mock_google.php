<?php
declare(strict_types=1);
/**
 * Stand-in for Google's OAuth token endpoint and the Play Developer API, used by tests/phase6_test.php.
 * Run: MOCK_STATE=/tmp/state.json MOCK_PUBKEY=/tmp/pub.pem php -S 127.0.0.1:8097 tests/mock_google.php
 * The API must run with APP_ENV=local and GOOGLE_TEST_BASE=http://127.0.0.1:8097.
 */
$state = getenv('MOCK_STATE');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');

function load(): array { return json_decode((string)@file_get_contents(getenv('MOCK_STATE')), true) ?: ['purchases' => [], 'log' => []]; }
function save(array $s): void { file_put_contents(getenv('MOCK_STATE'), json_encode($s), LOCK_EX); }
$b64d = fn(string $s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));

if ($path === '/token') {
    // Verify the RS256 JWT exactly as Google would: signature, audience, scope, expiry
    [$h, $c, $sig] = array_pad(explode('.', (string)($_POST['assertion'] ?? '')), 3, '');
    $claims = json_decode($b64d($c), true);
    $ok = openssl_verify("$h.$c", $b64d($sig), (string)file_get_contents(getenv('MOCK_PUBKEY')), OPENSSL_ALGO_SHA256) === 1
        && ($_POST['grant_type'] ?? '') === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && str_ends_with((string)($claims['aud'] ?? ''), '/token') && ($claims['exp'] ?? 0) > time();
    if (!$ok) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_grant']);
        exit;
    }
    $s = load();
    $s['log'][] = 'token:' . $claims['scope'];
    save($s);
    echo json_encode(['access_token' => 'mock-' . md5($claims['scope']), 'expires_in' => 3600, 'token_type' => 'Bearer']);
    exit;
}

if (!preg_match('#^/androidpublisher/v3/applications/([^/]+)/purchases/products/([^/]+)/tokens/([^/:]+)(:acknowledge)?$#', $path, $m)) {
    http_response_code(404);
    echo '{}';
    exit;
}
if (!str_starts_with((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'Bearer mock-')) {
    http_response_code(401);
    echo '{}';
    exit;
}
$s = load();
$token = urldecode($m[3]);
$p = $s['purchases'][$token] ?? null;
if (!$p || $p['productId'] !== urldecode($m[2]) || urldecode($m[1]) !== $s['package']) {
    http_response_code(404);
    echo json_encode(['error' => ['code' => 404]]);
    exit;
}
if (!empty($m[4])) {
    $s['purchases'][$token]['acknowledgementState'] = 1;
    $s['log'][] = 'ack:' . $token;
    save($s);
    http_response_code(204);
    exit;
}
unset($p['productId']);
echo json_encode(['kind' => 'androidpublisher#productPurchase'] + $p);
