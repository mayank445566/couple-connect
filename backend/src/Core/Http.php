<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown anywhere to produce a JSON error response. */
final class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }

    public static function validation(array $fields): self
    {
        return new self(422, 'validation_failed', 'Some fields are invalid.', $fields);
    }
}

final class Request
{
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly string $rawBody,
        private readonly array $headers,
        public array $params = [],
    ) {}

    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Support deployment in a sub-folder (e.g. /api/index.php/...)
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($script !== '' && str_starts_with($uri, $script)) {
            $uri = substr($uri, strlen($script));
        } elseif ($dir !== '' && str_starts_with($uri, $dir)) {
            $uri = substr($uri, strlen($dir));
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        // Some cPanel/Apache setups strip Authorization; .htaccess restores it here
        if (!isset($headers['authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($uri === '' ? '/' : $uri, '/'),
            $_GET,
            (string)file_get_contents('php://input'),
            $headers
        );
    }

    public function raw(): string
    {
        return $this->rawBody;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function json(): array
    {
        if ($this->json === null) {
            $ct = $this->header('content-type') ?? '';
            if ($this->rawBody === '' || !str_contains($ct, 'json')) {
                $this->json = $_POST ?: [];
            } else {
                $data = json_decode($this->rawBody, true);
                if (!is_array($data)) {
                    throw new ApiException(400, 'bad_json', 'Request body must be a JSON object.');
                }
                $this->json = $data;
            }
        }
        return $this->json;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json()[$key] ?? $default;
    }

    public function ip(): string
    {
        if (Config::bool('TRUST_PROXY')) {
            $cf = $this->header('cf-connecting-ip');
            if ($cf) {
                return $cf;
            }
            $xff = $this->header('x-forwarded-for');
            if ($xff) {
                return trim(explode(',', $xff)[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function bearerToken(): ?string
    {
        $h = $this->header('authorization');
        if ($h && preg_match('/^Bearer\s+([A-Za-z0-9_\-]{20,128})$/', trim($h), $m)) {
            return $m[1];
        }
        return null;
    }
}

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $status < 400, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(ApiException $e): never
    {
        http_response_code($e->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $err = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->fields) {
            $err['fields'] = $e->fields;
        }
        echo json_encode(['ok' => false, 'error' => $err], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
