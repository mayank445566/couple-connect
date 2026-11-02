<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Services\CoupleContext;
use App\Services\Present;

/**
 * GET /sync?since=<cursor>
 * Returns every change since the cursor plus partner presence. Call it every few
 * seconds while the chat is open, or immediately when a relay/push nudge arrives.
 */
final class SyncController
{
    private const ONLINE_WINDOW = 35; // seconds

    public function sync(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $since = max(0, (int)($req->query['since'] ?? 0));
        $limit = 200;

        $this->touch($ctx->userId);

        $events = Database::all(
            'SELECT id, actor_id, type, payload, created_at FROM couple_events WHERE couple_id = ? AND id > ? ORDER BY id LIMIT ' . ($limit + 1),
            [$ctx->id(), $since]
        );
        $hasMore = count($events) > $limit;
        $events = array_slice($events, 0, $limit);
        $cursor = $events ? (int)end($events)['id'] : $since;

        $out = [];
        $messageIds = [];
        foreach ($events as $e) {
            $payload = json_decode($e['payload'], true) ?: [];
            // Private events (delete-for-me, reads with receipts off) are only for the actor's own devices
            if (!empty($payload['private']) && (int)$e['actor_id'] !== $ctx->userId) {
                continue;
            }
            unset($payload['private']);
            if (str_starts_with($e['type'], 'message.') && isset($payload['id'])) {
                $messageIds[(int)$payload['id']] = true;
            }
            $out[] = [
                'id' => (int)$e['id'],
                'type' => $e['type'],
                'actor_id' => $e['actor_id'] !== null ? (int)$e['actor_id'] : null,
                'payload' => $payload,
                'at' => Present::iso($e['created_at']),
            ];
        }

        // Current state of every message touched by these events, in one query
        $messages = [];
        $hiddenIds = [];
        if ($messageIds) {
            $ids = array_keys($messageIds);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $rows = Database::all(
                MessageController::SELECT . " WHERE m.id IN ($in) AND " . MessageController::VISIBLE,
                array_merge($ids, [$ctx->id(), $ctx->userId])
            );
            $messages = Present::messages(MessageController::withReceiptPrivacy($rows, $ctx), $ctx->userId);
            $found = array_column($messages, 'id');
            $hiddenIds = array_values(array_diff($ids, $found)); // hidden by me or expired: remove locally
        }

        // Cursor older than the retained log (cron prunes after 30 days): client must reload
        $reset = false;
        if ($since > 0 && !$events) {
            $oldest = Database::value('SELECT MIN(id) FROM couple_events WHERE couple_id = ?', [$ctx->id()]);
            $reset = $oldest === null ? false : $since < (int)$oldest - 1 && !Database::value('SELECT 1 FROM couple_events WHERE couple_id = ? AND id <= ? LIMIT 1', [$ctx->id(), $since]);
        }

        return [
            'cursor' => $cursor,
            'has_more' => $hasMore,
            'reset' => $reset,
            'events' => $out,
            'messages' => $messages,
            'removed_message_ids' => $hiddenIds,
            'partner' => $this->partnerPresence($ctx->partnerId),
            'unread_count' => MessageController::unreadCount($ctx),
        ];
    }

    public function typing(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $typing = (bool)$req->input('typing', true);
        Database::run(
            'INSERT INTO presence (user_id, last_seen_at, typing_until) VALUES (?, UTC_TIMESTAMP(), ' . ($typing ? 'UTC_TIMESTAMP() + INTERVAL 6 SECOND' : 'NULL') . ')
             ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP(), typing_until = VALUES(typing_until)',
            [$ctx->userId]
        );
        return ['typing' => $typing];
    }

    private function touch(int $userId): void
    {
        Database::run(
            'INSERT INTO presence (user_id, last_seen_at) VALUES (?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP()',
            [$userId]
        );
    }

    private function partnerPresence(int $partnerId): array
    {
        $row = Database::one(
            'SELECT u.last_seen_visibility, p.last_seen_at, p.typing_until > UTC_TIMESTAMP() AS typing,
                    TIMESTAMPDIFF(SECOND, p.last_seen_at, UTC_TIMESTAMP()) AS idle
               FROM users u LEFT JOIN presence p ON p.user_id = u.id WHERE u.id = ?',
            [$partnerId]
        );
        $visible = $row && $row['last_seen_visibility'] === 'partner';
        return [
            'online' => $visible && $row['idle'] !== null && (int)$row['idle'] <= self::ONLINE_WINDOW,
            'typing' => (bool)($row['typing'] ?? false),
            'last_seen_at' => $visible ? Present::iso($row['last_seen_at']) : null,
        ];
    }
}
