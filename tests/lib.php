<?php
declare(strict_types=1);
// Shared helpers for the end-to-end tests.

$API = rtrim(getenv('API') ?: 'http://127.0.0.1:8088', '/');
$MAIL_LOG = getenv('MAIL_LOG') ?: __DIR__ . '/../backend/storage/logs/mail.log';
$SMS_LOG = getenv('SMS_LOG') ?: __DIR__ . '/../backend/storage/logs/sms.log';
$pass = 0;
$fail = 0;

function call(string $method, string $path, ?array $body = null, ?string $token = null, array $extraHeaders = [], array|string|null $multipart = null): array
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

function lastSms(string $phone): ?string
{
    global $SMS_LOG;
    clearstatcache();
    $lines = array_reverse(file($SMS_LOG) ?: []);
    foreach ($lines as $l) {
        if (str_contains($l, "To: $phone |") && preg_match('/\| (\d{6}) is your/', $l, $m)) {
            return $m[1];
        }
    }
    return null;
}

/** Registers + verifies a user by email; returns [token, id]. */
function makeUser(string $email, string $name, string $dob = '1995-03-10'): array
{
    $r = call('POST', '/auth/register', ['email' => $email, 'password' => 'Sunrise2026', 'display_name' => $name, 'dob' => $dob, 'accept_terms' => true]);
    $tok = $r['json']['data']['token'];
    call('POST', '/auth/verify-email', ['code' => (string)lastCode($email)], $tok);
    return [$tok, $r['json']['data']['user']['id']];
}

function pair(string $tokA, string $tokB): void
{
    $code = call('POST', '/couple/invite', null, $tokA)['json']['data']['code'];
    $req = call('POST', '/couple/join', ['code' => $code], $tokB)['json']['data']['request_id'];
    call('POST', "/couple/requests/$req/accept", null, $tokA);
}

function finish(): never
{
    global $pass, $fail;
    echo "\n$pass passed, $fail failed\n";
    exit($fail === 0 ? 0 : 1);
}
