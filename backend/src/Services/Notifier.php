<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Turns couple events into push notifications for the partner.
 *
 * Privacy rules:
 * - Message text is shown only if the recipient turned on "show preview"; otherwise "New message".
 * - 18+ content is never named in a notification ("invited you to play a game").
 * - Each category respects the recipient's switches and quiet hours. Calls ring regardless of quiet hours.
 * - No push if the recipient has the app open right now (they get it through sync instead).
 */
final class Notifier
{
    public static function onEvent(int $coupleId, ?int $actorId, string $type, array $payload): void
    {
        $map = [
            'message.created' => 'messages',
            'tap.sent' => 'messages',
            'game.invited' => 'games',
            'note.created' => 'reminders',
            'call.ringing' => 'calls',
            'call.ended' => 'calls',
        ];
        if (!isset($map[$type]) || Config::get('PUSH_DRIVER', 'off') === 'off') {
            return;
        }
        $couple = Database::one("SELECT user_a_id, user_b_id FROM couples WHERE id = ? AND status = 'active'", [$coupleId]);
        if (!$couple) {
            return;
        }
        $a = (int)$couple['user_a_id'];
        $b = (int)$couple['user_b_id'];
        // Scheduled letters are announced by cron with the author as actor: notify the other person
        $recipient = $actorId === $a ? $b : $a;
        $sender = $recipient === $a ? $b : $a;
        $category = $map[$type];

        if ($type === 'call.ended' && !in_array($payload['reason'] ?? '', ['cancelled', 'missed'], true)) {
            return; // only a missed call needs a notice
        }

        $prefs = Database::one('SELECT * FROM user_preferences WHERE user_id = ?', [$recipient]) ?? [];
        $enabled = match ($category) {
            'messages' => (bool)($prefs['notify_messages'] ?? true),
            'games' => (bool)($prefs['notify_games'] ?? true),
            'reminders' => (bool)($prefs['notify_reminders'] ?? true),
            default => true,
        };
        if (!$enabled) {
            return;
        }
        if ($category !== 'calls') {
            if (self::inQuietHours($prefs['quiet_start'] ?? null, $prefs['quiet_end'] ?? null)) {
                return;
            }
            $seen = Database::value('SELECT last_seen_at > UTC_TIMESTAMP() - INTERVAL 20 SECOND FROM presence WHERE user_id = ?', [$recipient]);
            if ($seen) {
                return;
            }
        }

        $name = (string)Database::value('SELECT display_name FROM users WHERE id = ?', [$sender]);
        $app = Config::get('APP_NAME', 'Couple Connect');
        $data = ['type' => $type, 'couple_id' => $coupleId];

        switch ($type) {
            case 'message.created':
                $body = 'New message';
                if (!empty($prefs['show_preview'])) {
                    $m = Database::one('SELECT type, body FROM messages WHERE id = ?', [$payload['id'] ?? 0]);
                    if ($m) {
                        $body = match ($m['type']) {
                            'text' => mb_strimwidth((string)$m['body'], 0, 120, '…'),
                            'image' => '📷 Photo',
                            'video' => '🎬 Video',
                            'voice' => '🎤 Voice note',
                            'file' => '📎 File',
                            default => 'New message',
                        };
                    }
                }
                Push::queue($recipient, $name, $body, $data + ['url' => '/chat', 'tag' => 'chat'], 'messages');
                break;
            case 'tap.sent':
                $labels = ['hug' => 'a hug 🤗', 'kiss' => 'a kiss 😘', 'heartbeat' => 'their heartbeat 💓', 'miss_you' => '“I miss you” 🥺', 'thinking_of_you' => '“thinking of you” 💭', 'good_morning' => 'good morning ☀️', 'good_night' => 'good night 🌙'];
                Push::queue($recipient, $app, $name . ' sent you ' . ($labels[$payload['kind'] ?? ''] ?? 'some love 💗'), $data + ['url' => '/', 'tag' => 'tap'], 'messages');
                break;
            case 'game.invited':
                Push::queue($recipient, $app, $name . ' invited you to play a game', $data + ['url' => '/game/' . ($payload['session_id'] ?? ''), 'tag' => 'game'], 'games');
                break;
            case 'note.created':
                $isLetter = ($payload['kind'] ?? '') === 'letter';
                Push::queue($recipient, $app, $isLetter ? "A letter from $name is ready to open 💌" : "$name added a note", $data + ['url' => '/notes', 'tag' => 'note'], 'reminders');
                break;
            case 'call.ringing':
                $video = ($payload['kind'] ?? '') === 'video';
                Push::queue($recipient, $name, $video ? 'Incoming video call' : 'Incoming voice call', $data + ['url' => '/call/' . ($payload['call_id'] ?? ''), 'call_id' => $payload['call_id'] ?? '', 'tag' => 'call'], 'calls');
                break;
            case 'call.ended':
                Push::queue($recipient, $app, "Missed call from $name", $data + ['url' => '/chat', 'tag' => 'call'], 'messages');
                break;
        }
    }

    /** Quiet hours are in the app's time zone (APP_TIMEZONE), e.g. 23:00–07:00 wraps midnight. */
    public static function inQuietHours(?string $start, ?string $end, ?\DateTimeImmutable $now = null): bool
    {
        if (!$start || !$end || $start === $end) {
            return false;
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone(Config::get('APP_TIMEZONE', 'Asia/Kolkata')));
        $t = $now->format('H:i');
        $s = substr($start, 0, 5);
        $e = substr($end, 0, 5);
        return $s < $e ? ($t >= $s && $t < $e) : ($t >= $s || $t < $e);
    }
}
