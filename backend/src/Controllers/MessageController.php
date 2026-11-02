<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Services\CoupleContext;
use App\Services\Events;
use App\Services\Present;

final class MessageController
{
    public const SELECT = 'SELECT m.*, md.mime AS media_mime, md.size_bytes AS media_size, md.width AS media_width,
                                  md.height AS media_height, md.duration_ms AS media_duration, md.original_name AS media_name
                             FROM messages m LEFT JOIN media md ON md.id = m.media_id';

    /** Rows the viewer may see: their couple, not hidden by them, not expired. */
    public const VISIBLE = 'm.couple_id = ? AND (m.expires_at IS NULL OR m.expires_at > UTC_TIMESTAMP())
                            AND NOT EXISTS (SELECT 1 FROM message_hidden h WHERE h.message_id = m.id AND h.user_id = ?)';

    private const MEDIA_TYPES = ['image' => ['image'], 'video' => ['video'], 'audio' => ['audio', 'voice'], 'voice' => ['voice', 'audio'], 'file' => ['file', 'image', 'video', 'audio']];

    /** Newest-first page. Use ?before=<id> to load older messages. */
    public function index(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $limit = max(1, min(100, (int)($req->query['limit'] ?? 30)));
        $before = isset($req->query['before']) ? (int)$req->query['before'] : null;
        $sql = self::SELECT . ' WHERE ' . self::VISIBLE . ($before ? ' AND m.id < ?' : '') . ' ORDER BY m.id DESC LIMIT ' . ($limit + 1);
        $params = [$ctx->id(), $ctx->userId];
        if ($before) {
            $params[] = $before;
        }
        $rows = Database::all($sql, $params);
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        return [
            'messages' => Present::messages(self::withReceiptPrivacy($rows, $ctx), $ctx->userId),
            'has_more' => $hasMore,
            // Start syncing from here so nothing between this page and the first sync is missed
            'cursor' => (int)Database::value('SELECT COALESCE(MAX(id), 0) FROM couple_events WHERE couple_id = ?', [$ctx->id()]),
            'unread_count' => self::unreadCount($ctx),
        ];
    }

    public function store(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('msg:user:' . $ctx->userId, 120, 60);
        $d = Validator::check($req->json(), [
            'client_id' => 'required,uuid',
            'type' => 'in:text|image|video|audio|voice|file|sticker|gif',
            'body' => 'string,max:4000',
            'media_id' => 'int',
            'reply_to_id' => 'int',
            'forward_of' => 'int',
        ]);
        $clientId = strtolower($d['client_id']);

        // Idempotent retries: same client_id returns the original message
        if ($existing = $this->findByClientId($ctx, $clientId)) {
            return $existing;
        }

        $type = $d['type'] ?? 'text';
        $body = $d['body'] ?? null;
        $mediaId = $d['media_id'] ?? null;
        $meta = null;
        $forwarded = 0;

        if (isset($d['forward_of'])) {
            $src = Database::one('SELECT * FROM messages m WHERE m.id = ? AND ' . self::VISIBLE . ' AND m.deleted_at IS NULL', [$d['forward_of'], $ctx->id(), $ctx->userId]);
            if (!$src) {
                throw ApiException::validation(['forward_of' => 'Message not found.']);
            }
            [$type, $body, $mediaId, $meta, $forwarded] = [$src['type'], $src['body'], $src['media_id'], $src['meta'], 1];
        } elseif ($type === 'text') {
            if ($body === null || $body === '') {
                throw ApiException::validation(['body' => 'Message cannot be empty.']);
            }
        } elseif (in_array($type, ['sticker', 'gif'], true)) {
            $meta = $this->stickerMeta($req->input('meta'), $type);
        } else {
            if (!$mediaId) {
                throw ApiException::validation(['media_id' => 'Upload the file first.']);
            }
            $media = Database::one('SELECT * FROM media WHERE id = ? AND owner_id = ? AND deleted_at IS NULL', [$mediaId, $ctx->userId]);
            if (!$media || ($media['couple_id'] !== null && (int)$media['couple_id'] !== $ctx->id()) || !in_array($media['kind'], self::MEDIA_TYPES[$type], true)) {
                throw ApiException::validation(['media_id' => 'This file cannot be sent as ' . $type . '.']);
            }
            if ($body !== null && $body !== '') {
                $meta = json_encode(['caption' => true]);
            }
        }

        $replyTo = $d['reply_to_id'] ?? null;
        if ($replyTo && !Database::value('SELECT 1 FROM messages m WHERE m.id = ? AND m.couple_id = ?', [$replyTo, $ctx->id()])) {
            throw ApiException::validation(['reply_to_id' => 'Message not found.']);
        }
        $ttl = (int)$ctx->couple['disappearing_seconds'];

        try {
            $id = Database::transaction(function () use ($ctx, $clientId, $type, $body, $mediaId, $meta, $replyTo, $forwarded, $ttl) {
                $id = Database::insert(
                    'INSERT INTO messages (couple_id, sender_id, client_id, type, body, media_id, meta, reply_to_id, forwarded, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($ttl > 0 ? 'UTC_TIMESTAMP() + INTERVAL ' . $ttl . ' SECOND' : 'NULL') . ')',
                    [$ctx->id(), $ctx->userId, $clientId, $type, $body, $mediaId, $meta, $replyTo, $forwarded]
                );
                Events::emit($ctx->id(), $ctx->userId, 'message.created', ['id' => $id]);
                return $id;
            });
        } catch (\PDOException $e) {
            // Two identical retries racing each other: the unique key wins, return the stored one
            if ($e->getCode() === '23000' && ($existing = $this->findByClientId($ctx, $clientId))) {
                return $existing;
            }
            throw $e;
        }
        Database::run('UPDATE presence SET typing_until = NULL WHERE user_id = ?', [$ctx->userId]);
        return Router::status(201, $this->load($ctx, $id));
    }

    public function update(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $d = Validator::check($req->json(), ['body' => 'required,string,max:4000']);
        $m = $this->own($ctx, (int)$req->params['id']);
        if ($m['type'] !== 'text') {
            throw new ApiException(422, 'not_editable', 'Only text messages can be edited.');
        }
        $this->assertWithin($m, Config::int('EDIT_WINDOW_MINUTES', 1440), 'edit');
        Database::run('UPDATE messages SET body = ?, edited_at = UTC_TIMESTAMP() WHERE id = ?', [$d['body'], $m['id']]);
        Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    /** ?scope=me hides it on my devices; ?scope=everyone removes it for both. */
    public function destroy(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $id = (int)$req->params['id'];
        $scope = $req->query['scope'] ?? 'me';
        if ($scope === 'everyone') {
            $m = $this->own($ctx, $id);
            $this->assertWithin($m, Config::int('DELETE_FOR_EVERYONE_WINDOW_MINUTES', 2880), 'delete for everyone');
            Database::transaction(function () use ($m, $ctx) {
                Database::run('UPDATE messages SET deleted_at = UTC_TIMESTAMP(), body = NULL, meta = NULL, pinned_at = NULL WHERE id = ?', [$m['id']]);
                Database::run('DELETE FROM message_reactions WHERE message_id = ?', [$m['id']]);
                if ($m['media_id']) {
                    // File is removed by cron unless another message still uses it (forwarded copies)
                    Database::run('UPDATE media SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND NOT EXISTS (SELECT 1 FROM messages WHERE media_id = ? AND id <> ? AND deleted_at IS NULL)', [$m['media_id'], $m['media_id'], $m['id']]);
                }
                Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
            });
            return ['deleted' => 'everyone'];
        }
        $this->visible($ctx, $id);
        Database::run('INSERT IGNORE INTO message_hidden (user_id, message_id) VALUES (?, ?)', [$ctx->userId, $id]);
        Events::emit($ctx->id(), $ctx->userId, 'message.hidden', ['id' => $id, 'private' => true]);
        return ['deleted' => 'me'];
    }

    public function react(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $emoji = (string)$req->input('emoji', '');
        if ($emoji === '' || strlen($emoji) > 16 || preg_match('/[\x00-\x7F]/', $emoji)) {
            throw ApiException::validation(['emoji' => 'Pick an emoji.']);
        }
        $m = $this->visible($ctx, (int)$req->params['id']);
        if ($m['deleted_at'] !== null) {
            throw new ApiException(422, 'deleted', 'This message was deleted.');
        }
        Database::run(
            'INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = UTC_TIMESTAMP()',
            [$m['id'], $ctx->userId, $emoji]
        );
        Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function unreact(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $m = $this->visible($ctx, (int)$req->params['id']);
        Database::run('DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?', [$m['id'], $ctx->userId]);
        Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function pin(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $m = $this->visible($ctx, (int)$req->params['id']);
        if ($m['deleted_at'] !== null) {
            throw new ApiException(422, 'deleted', 'This message was deleted.');
        }
        Database::run('UPDATE messages SET pinned_at = UTC_TIMESTAMP(), pinned_by = ? WHERE id = ?', [$ctx->userId, $m['id']]);
        Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function unpin(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $m = $this->visible($ctx, (int)$req->params['id']);
        Database::run('UPDATE messages SET pinned_at = NULL, pinned_by = NULL WHERE id = ?', [$m['id']]);
        Events::emit($ctx->id(), $ctx->userId, 'message.updated', ['id' => (int)$m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function star(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $m = $this->visible($ctx, (int)$req->params['id']);
        Database::run('INSERT IGNORE INTO message_stars (user_id, message_id) VALUES (?, ?)', [$ctx->userId, $m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function unstar(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $m = $this->visible($ctx, (int)$req->params['id']);
        Database::run('DELETE FROM message_stars WHERE user_id = ? AND message_id = ?', [$ctx->userId, $m['id']]);
        return $this->load($ctx, (int)$m['id']);
    }

    public function starred(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $rows = Database::all(
            self::SELECT . ' JOIN message_stars s ON s.message_id = m.id AND s.user_id = ? WHERE ' . self::VISIBLE . ' AND m.deleted_at IS NULL ORDER BY s.created_at DESC LIMIT 200',
            [$ctx->userId, $ctx->id(), $ctx->userId]
        );
        return Present::messages(self::withReceiptPrivacy($rows, $ctx), $ctx->userId);
    }

    public function pinned(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $rows = Database::all(self::SELECT . ' WHERE ' . self::VISIBLE . ' AND m.pinned_at IS NOT NULL AND m.deleted_at IS NULL ORDER BY m.pinned_at DESC LIMIT 50', [$ctx->id(), $ctx->userId]);
        return Present::messages(self::withReceiptPrivacy($rows, $ctx), $ctx->userId);
    }

    public function search(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $q = trim((string)($req->query['q'] ?? ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 100) {
            throw ApiException::validation(['q' => 'Search for 2 to 100 characters.']);
        }
        RateLimiter::hit('search:user:' . $ctx->userId, 60, 60);
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = Database::all(
            self::SELECT . ' WHERE ' . self::VISIBLE . " AND m.deleted_at IS NULL AND m.body LIKE ? ORDER BY m.id DESC LIMIT 50",
            [$ctx->id(), $ctx->userId, $like]
        );
        return Present::messages(self::withReceiptPrivacy($rows, $ctx), $ctx->userId);
    }

    /** Marks partner's messages up to an id as delivered (app received them). */
    public function delivered(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $upTo = (int)$req->input('up_to_id', 0);
        $n = Database::run(
            'UPDATE messages SET delivered_at = UTC_TIMESTAMP() WHERE couple_id = ? AND sender_id = ? AND id <= ? AND delivered_at IS NULL',
            [$ctx->id(), $ctx->partnerId, $upTo]
        )->rowCount();
        if ($n > 0) {
            Events::emit($ctx->id(), $ctx->userId, 'message.delivered', ['up_to_id' => $upTo]);
        }
        return ['updated' => $n];
    }

    /** Marks partner's messages up to an id as read. Hidden from the partner if read receipts are off. */
    public function read(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $upTo = (int)$req->input('up_to_id', 0);
        $n = Database::run(
            'UPDATE messages SET read_at = UTC_TIMESTAMP(), delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP())
              WHERE couple_id = ? AND sender_id = ? AND id <= ? AND read_at IS NULL',
            [$ctx->id(), $ctx->partnerId, $upTo]
        )->rowCount();
        if ($n > 0) {
            Events::emit($ctx->id(), $ctx->userId, 'message.read', ['up_to_id' => $upTo, 'private' => !$user['read_receipts']]);
        }
        return ['updated' => $n, 'unread_count' => self::unreadCount($ctx)];
    }

    // ---- helpers ---------------------------------------------------------

    public static function unreadCount(CoupleContext $ctx): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM messages m WHERE ' . self::VISIBLE . ' AND m.sender_id = ? AND m.read_at IS NULL AND m.deleted_at IS NULL',
            [$ctx->id(), $ctx->userId, $ctx->partnerId]
        );
    }

    /** Flags rows so Present hides read_at when the partner turned read receipts off. */
    public static function withReceiptPrivacy(array $rows, CoupleContext $ctx): array
    {
        static $cache = [];
        $hidden = $cache[$ctx->partnerId] ??= !(bool)Database::value('SELECT read_receipts FROM users WHERE id = ?', [$ctx->partnerId]);
        if ($hidden) {
            foreach ($rows as &$r) {
                $r['receipts_hidden'] = true;
            }
        }
        return $rows;
    }

    private function load(CoupleContext $ctx, int $id): array
    {
        $rows = Database::all(self::SELECT . ' WHERE m.id = ? AND m.couple_id = ?', [$id, $ctx->id()]);
        return Present::messages(self::withReceiptPrivacy($rows, $ctx), $ctx->userId)[0];
    }

    private function findByClientId(CoupleContext $ctx, string $clientId): ?array
    {
        $id = Database::value('SELECT id FROM messages WHERE sender_id = ? AND client_id = ? AND couple_id = ?', [$ctx->userId, $clientId, $ctx->id()]);
        return $id ? $this->load($ctx, (int)$id) : null;
    }

    private function visible(CoupleContext $ctx, int $id): array
    {
        $m = Database::one('SELECT * FROM messages m WHERE m.id = ? AND ' . self::VISIBLE, [$id, $ctx->id(), $ctx->userId]);
        if (!$m) {
            throw new ApiException(404, 'not_found', 'Message not found.');
        }
        return $m;
    }

    private function own(CoupleContext $ctx, int $id): array
    {
        $m = $this->visible($ctx, $id);
        if ((int)$m['sender_id'] !== $ctx->userId) {
            throw new ApiException(403, 'not_yours', 'You can only change your own messages.');
        }
        if ($m['deleted_at'] !== null) {
            throw new ApiException(422, 'deleted', 'This message was deleted.');
        }
        return $m;
    }

    private function assertWithin(array $m, int $minutes, string $action): void
    {
        if (strtotime($m['created_at'] . ' UTC') < time() - $minutes * 60) {
            throw new ApiException(422, 'too_old', "This message is too old to $action.");
        }
    }

    private function stickerMeta(mixed $meta, string $type): string
    {
        if (!is_array($meta)) {
            throw ApiException::validation(['meta' => 'Sticker details are missing.']);
        }
        if (isset($meta['sticker_id']) && is_string($meta['sticker_id']) && preg_match('/^[a-z0-9_\-\/]{1,80}$/', $meta['sticker_id'])) {
            return json_encode(['sticker_id' => $meta['sticker_id']]);
        }
        // GIFs from a configured provider: only https URLs, stored as a reference
        $url = $meta['url'] ?? null;
        if ($type === 'gif' && is_string($url) && strlen($url) <= 500 && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)) {
            $out = ['url' => $url];
            foreach (['width', 'height'] as $k) {
                if (isset($meta[$k]) && is_int($meta[$k])) {
                    $out[$k] = $meta[$k];
                }
            }
            return json_encode($out);
        }
        throw ApiException::validation(['meta' => 'Invalid sticker or GIF.']);
    }
}
