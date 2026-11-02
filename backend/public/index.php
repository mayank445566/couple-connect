<?php
declare(strict_types=1);

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

// HTTPS only in production
if (Config::get('APP_ENV') === 'production') {
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if (!$https) {
        Response::error(new ApiException(403, 'https_required', 'Use HTTPS.'));
    }
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// The mobile app does not need CORS; this only allows an optional web client on APP_URL's origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed = array_filter(array_map('trim', explode(',', (string)Config::get('CORS_ORIGINS', ''))));
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Range');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Server-rendered admin panel (Phase 5)
$__req = Request::fromGlobals();
if ($__req->path === '/admin' || str_starts_with($__req->path, '/admin/')) {
    \App\Admin\Kernel::handle($__req);
}

try {
    $router = require BASE_PATH . '/src/routes.php';
    $router->dispatch($__req);
} catch (ApiException $e) {
    Response::error($e);
} catch (\Throwable $e) {
    $ref = bin2hex(random_bytes(4));
    error_log("[$ref] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = Config::get('APP_ENV') === 'local' ? $e->getMessage() : "Something went wrong (ref $ref).";
    Response::error(new ApiException(500, 'server_error', $msg));
}
