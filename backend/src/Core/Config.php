<?php
declare(strict_types=1);

namespace App\Core;

/** Minimal .env loader (no external dependency, works on shared hosting). */
final class Config
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Missing .env file. Copy .env.example to .env and configure it.');
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $q = $value[0];
                $end = strpos($value, $q, 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }
            self::$values[$key] = $value;
        }
        // Allow environment overrides (useful in tests/CI)
        foreach (self::$values as $k => $_) {
            $env = getenv($k);
            if ($env !== false) {
                self::$values[$k] = $env;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = self::$values[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Test shortcuts (fake Razorpay orders, local Google stand-in) work only when APP_ENV=local AND the API
     * address is a local one, so a production server left on APP_ENV=local cannot hand out free Premium.
     */
    public static function devMode(): bool
    {
        if (self::get('APP_ENV') !== 'local') {
            return false;
        }
        $host = (string)parse_url((string)self::get('APP_URL', ''), PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1', '10.0.2.2'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');
    }

    public static function require(string $key): string
    {
        $v = self::get($key);
        if ($v === null) {
            throw new \RuntimeException("Configuration value $key is required");
        }
        return $v;
    }

    /** Binary key from a 64-hex config value. */
    public static function key(string $key): string
    {
        $hex = self::require($key);
        if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
            throw new \RuntimeException("$key must be 64 hex characters");
        }
        return hex2bin($hex);
    }

    public static function storage(string $sub = ''): string
    {
        $base = rtrim(self::get('STORAGE_PATH', BASE_PATH . '/storage'), '/');
        $path = $sub === '' ? $base : $base . '/' . $sub;
        if (!is_dir($path)) {
            @mkdir($path, 0750, true);
        }
        return $path;
    }
}
