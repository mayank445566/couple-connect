<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Append-only change log per couple. Clients sync from it with a cursor, which
 * makes edits, deletes, reactions and receipts consistent on every device.
 * If a relay is configured, partners with an open socket get an instant nudge.
 */
final class Events
{
    public static function emit(int $coupleId, ?int $actorId, string $type, array $payload): int
    {
        $id = Database::insert(
            'INSERT INTO couple_events (couple_id, actor_id, type, payload) VALUES (?, ?, ?, ?)',
            [$coupleId, $actorId, $type, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
        self::nudge($coupleId, $id, $type);
        Notifier::onEvent($coupleId, $actorId, $type, $payload);
        return $id;
    }

    /** Nudge without writing to the log: for high-frequency, short-lived data such as call signals. */
    public static function ping(int $coupleId, string $type, array $extra = []): void
    {
        self::nudge($coupleId, 0, $type, $extra);
    }

    /** Fire-and-forget POST to the relay. Never contains message content. */
    private static function nudge(int $coupleId, int $eventId, string $type, array $extra = []): void
    {
        $url = Config::get('RELAY_URL');
        $secret = Config::get('RELAY_SECRET');
        if (!$url || !$secret || !function_exists('curl_init')) {
            return;
        }
        $body = json_encode(['couple_id' => $coupleId, 'event_id' => $eventId, 'type' => $type, 'ts' => time()] + $extra);
        $ch = curl_init(rtrim($url, '/') . '/publish');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Signature: ' . hash_hmac('sha256', $body, $secret),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 1000,
        ]);
        curl_exec($ch); // failures are fine: clients fall back to polling
        curl_close($ch);
    }
}
