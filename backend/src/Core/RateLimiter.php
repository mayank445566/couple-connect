<?php
declare(strict_types=1);

namespace App\Core;

/** Fixed-window limiter stored in MySQL (no Redis needed on shared hosting). */
final class RateLimiter
{
    /** Counts a hit; throws 429 when the limit is exceeded. */
    public static function hit(string $key, int $max, int $windowSeconds): void
    {
        $k = substr($key, 0, 191);
        Database::run(
            'INSERT INTO rate_limits (k, hits, reset_at) VALUES (?, 1, UTC_TIMESTAMP() + INTERVAL ? SECOND)
             ON DUPLICATE KEY UPDATE
               hits = IF(reset_at <= UTC_TIMESTAMP(), 1, hits + 1),
               reset_at = IF(reset_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? SECOND, reset_at)',
            [$k, $windowSeconds, $windowSeconds]
        );
        $row = Database::one('SELECT hits, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), reset_at) AS ttl FROM rate_limits WHERE k = ?', [$k]);
        if ($row && (int)$row['hits'] > $max) {
            header('Retry-After: ' . max(1, (int)$row['ttl']));
            throw new ApiException(429, 'rate_limited', 'Too many attempts. Please wait and try again.');
        }
    }

    /** True when the key is currently over its limit, without counting a hit. */
    public static function blocked(string $key, int $max): bool
    {
        $hits = Database::value('SELECT hits FROM rate_limits WHERE k = ? AND reset_at > UTC_TIMESTAMP()', [substr($key, 0, 191)]);
        return $hits !== null && (int)$hits >= $max;
    }

    public static function clear(string $key): void
    {
        Database::run('DELETE FROM rate_limits WHERE k = ?', [substr($key, 0, 191)]);
    }
}
