<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/** Shapes database rows into API output. Keeps private columns out of responses. */
final class Present
{
    public static function iso(?string $dt): ?string
    {
        return $dt === null ? null : gmdate('Y-m-d\TH:i:s', strtotime($dt . ' UTC')) . (str_contains($dt, '.') ? substr($dt, strpos($dt, '.'), 4) : '') . 'Z';
    }

    public static function me(array $u): array
    {
        return [
            'id' => (int)$u['id'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'display_name' => $u['display_name'],
            'dob' => $u['dob'],
            'age' => \App\Core\Validator::age($u['dob']),
            'relationship_status' => $u['relationship_status'],
            'bio' => $u['bio'],
            'avatar_url' => self::mediaUrl($u['avatar_media_id'] ? (int)$u['avatar_media_id'] : null),
            'email_verified' => $u['email_verified_at'] !== null,
            'phone_verified' => $u['phone_verified_at'] !== null,
            'paired' => $u['active_couple_id'] !== null,
            'couple_id' => $u['active_couple_id'] !== null ? (int)$u['active_couple_id'] : null,
            'privacy' => [
                'last_seen_visibility' => $u['last_seen_visibility'],
                'read_receipts' => (bool)$u['read_receipts'],
            ],
            'adult_opt_in' => $u['adult_opt_in_at'] !== null,
            'created_at' => self::iso($u['created_at']),
        ];
    }

    /** What a partner (or a pairing request counterpart) may see. */
    public static function partner(array $u): array
    {
        return [
            'id' => (int)$u['id'],
            'display_name' => $u['display_name'],
            'relationship_status' => $u['relationship_status'],
            'bio' => $u['bio'] ?? null,
            'avatar_url' => self::mediaUrl(isset($u['avatar_media_id']) ? (int)$u['avatar_media_id'] ?: null : null),
            'birthday' => isset($u['dob']) ? substr($u['dob'], 5) : null, // MM-DD only, year stays private
        ];
    }

    public static function mediaUrl(?int $mediaId): ?string
    {
        return $mediaId ? rtrim((string)Config::get('APP_URL', ''), '/') . '/media/' . $mediaId : null;
    }

    /** Batch-shapes messages for a viewer, loading reactions/stars in two queries. */
    public static function messages(array $rows, int $viewerId): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $reactions = [];
        foreach (Database::all("SELECT message_id, user_id, emoji FROM message_reactions WHERE message_id IN ($in)", $ids) as $r) {
            $reactions[(int)$r['message_id']][] = ['user_id' => (int)$r['user_id'], 'emoji' => $r['emoji']];
        }
        $starred = array_flip(array_map('intval', Database::run(
            "SELECT message_id FROM message_stars WHERE user_id = ? AND message_id IN ($in)",
            array_merge([$viewerId], $ids)
        )->fetchAll(\PDO::FETCH_COLUMN)));

        return array_map(fn($m) => self::message($m, $viewerId, $reactions[(int)$m['id']] ?? [], isset($starred[(int)$m['id']])), $rows);
    }

    public static function message(array $m, int $viewerId, array $reactions = [], bool $starred = false): array
    {
        $deleted = $m['deleted_at'] !== null;
        $mine = (int)$m['sender_id'] === $viewerId;
        return [
            'id' => (int)$m['id'],
            'client_id' => $m['client_id'],
            'sender_id' => (int)$m['sender_id'],
            'mine' => $mine,
            'type' => $deleted ? 'deleted' : $m['type'],
            'body' => $deleted ? null : $m['body'],
            'media' => $deleted || !$m['media_id'] ? null : [
                'id' => (int)$m['media_id'],
                'url' => self::mediaUrl((int)$m['media_id']),
                'mime' => $m['media_mime'] ?? null,
                'size' => isset($m['media_size']) ? (int)$m['media_size'] : null,
                'width' => isset($m['media_width']) ? (int)$m['media_width'] : null,
                'height' => isset($m['media_height']) ? (int)$m['media_height'] : null,
                'duration_ms' => isset($m['media_duration']) ? (int)$m['media_duration'] : null,
                'name' => $m['media_name'] ?? null,
            ],
            'meta' => $deleted || $m['meta'] === null ? null : json_decode($m['meta'], true),
            'reply_to_id' => $m['reply_to_id'] !== null ? (int)$m['reply_to_id'] : null,
            'forwarded' => (bool)$m['forwarded'],
            'reactions' => $deleted ? [] : $reactions,
            'starred' => $starred,
            'pinned' => !$deleted && $m['pinned_at'] !== null,
            'created_at' => self::iso($m['created_at']),
            'edited_at' => self::iso($m['edited_at']),
            'deleted' => $deleted,
            // Partner's receipts are hidden if the reader disabled read receipts
            'delivered_at' => self::iso($m['delivered_at']),
            'read_at' => ($mine && !empty($m['receipts_hidden'])) ? null : self::iso($m['read_at']),
            'expires_at' => self::iso($m['expires_at']),
        ];
    }
}
