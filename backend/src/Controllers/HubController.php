<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Services\CoupleContext;
use App\Services\Events;
use App\Services\Present;

/**
 * Long-distance hub and shared couple space. Everything is scoped to the caller's
 * active couple; nothing here is visible to anyone else, including admins.
 */
final class HubController
{
    /**
     * Couple-scoped lists that share the same CRUD shape.
     * fields: name => validation rule; dt: datetime fields (ISO in, ISO out); bool: flags.
     */
    private const RESOURCES = [
        'countdowns' => ['table' => 'countdowns', 'owner' => 'created_by', 'order' => 'target_at ASC',
            'fields' => ['title' => 'required,string,min:1,max:100', 'target_at' => 'required,string,max:40', 'emoji' => 'string,max:16'],
            'dt' => ['target_at'], 'bool' => []],
        'bucket' => ['table' => 'bucket_items', 'owner' => 'created_by', 'order' => 'done_at IS NOT NULL, id DESC',
            'fields' => ['title' => 'required,string,min:1,max:150', 'notes' => 'string,max:1000', 'category' => 'string,max:30'],
            'dt' => [], 'bool' => []],
        'calendar' => ['table' => 'calendar_events', 'owner' => 'created_by', 'order' => 'starts_at ASC',
            'fields' => ['title' => 'required,string,min:1,max:150', 'kind' => 'in:date|birthday|anniversary|reminder|trip|call|other',
                         'starts_at' => 'required,string,max:40', 'ends_at' => 'string,max:40', 'all_day' => 'bool', 'repeat_yearly' => 'bool',
                         'remind_minutes' => 'int', 'notes' => 'string,max:1000'],
            'dt' => ['starts_at', 'ends_at'], 'bool' => ['all_day', 'repeat_yearly']],
        'memories' => ['table' => 'memories', 'owner' => 'created_by', 'order' => 'happened_on DESC, id DESC',
            'fields' => ['title' => 'required,string,min:1,max:150', 'body' => 'string,max:2000', 'media_id' => 'int',
                         'happened_on' => 'required,date', 'is_milestone' => 'bool', 'is_favorite' => 'bool'],
            'dt' => [], 'bool' => ['is_milestone', 'is_favorite']],
    ];

    // ---- generic couple lists ------------------------------------------

    public function list(Request $req, array $user): array
    {
        [$ctx, $cfg] = $this->resource($req, $user);
        $sql = "SELECT * FROM {$cfg['table']} WHERE couple_id = ?";
        $params = [$ctx->id()];
        if ($cfg['table'] === 'calendar_events' && isset($req->query['from'])) {
            $sql .= ' AND (starts_at >= ? OR repeat_yearly = 1)';
            $params[] = $this->dt((string)$req->query['from'], 'from');
        }
        $rows = Database::all($sql . " ORDER BY {$cfg['order']} LIMIT 500", $params);
        return array_map(fn($r) => $this->present($r, $cfg), $rows);
    }

    public function create(Request $req, array $user): array
    {
        [$ctx, $cfg] = $this->resource($req, $user);
        RateLimiter::hit('hub-create:user:' . $ctx->userId, 200, 3600);
        $d = $this->clean($req, $cfg, true, $ctx);
        $d['couple_id'] = $ctx->id();
        $d[$cfg['owner']] = $ctx->userId;
        $cols = array_keys($d);
        $id = Database::insert(
            "INSERT INTO {$cfg['table']} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_values($d)
        );
        Events::emit($ctx->id(), $ctx->userId, 'hub.changed', ['resource' => $req->params['slug'], 'id' => $id]);
        return Router::status(201, $this->present(Database::one("SELECT * FROM {$cfg['table']} WHERE id = ?", [$id]), $cfg));
    }

    public function update(Request $req, array $user): array
    {
        [$ctx, $cfg] = $this->resource($req, $user);
        $row = $this->find($cfg, $ctx, (int)$req->params['id']);
        $d = $this->clean($req, $cfg, false, $ctx);
        if ($cfg['table'] === 'bucket_items' && array_key_exists('done', $req->json())) {
            $done = (bool)$req->input('done');
            Database::run('UPDATE bucket_items SET done_at = ' . ($done ? 'UTC_TIMESTAMP()' : 'NULL') . ', done_by = ? WHERE id = ?', [$done ? $ctx->userId : null, $row['id']]);
        }
        if ($d) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
            Database::run("UPDATE {$cfg['table']} SET $sets WHERE id = ?", [...array_values($d), $row['id']]);
        }
        Events::emit($ctx->id(), $ctx->userId, 'hub.changed', ['resource' => $req->params['slug'], 'id' => (int)$row['id']]);
        return $this->present(Database::one("SELECT * FROM {$cfg['table']} WHERE id = ?", [$row['id']]), $cfg);
    }

    public function delete(Request $req, array $user): array
    {
        [$ctx, $cfg] = $this->resource($req, $user);
        $row = $this->find($cfg, $ctx, (int)$req->params['id']);
        Database::run("DELETE FROM {$cfg['table']} WHERE id = ?", [$row['id']]);
        Events::emit($ctx->id(), $ctx->userId, 'hub.changed', ['resource' => $req->params['slug'], 'id' => (int)$row['id'], 'deleted' => true]);
        return ['deleted' => true];
    }

    // ---- love taps -----------------------------------------------------

    public function tap(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('tap:user:' . $ctx->userId, 30, 60);
        $d = Validator::check($req->json(), ['kind' => 'required,in:hug|kiss|heartbeat|miss_you|thinking_of_you|good_morning|good_night']);
        $id = Database::insert('INSERT INTO love_taps (couple_id, sender_id, kind) VALUES (?, ?, ?)', [$ctx->id(), $ctx->userId, $d['kind']]);
        Events::emit($ctx->id(), $ctx->userId, 'tap.sent', ['id' => $id, 'kind' => $d['kind']]);
        return Router::status(201, ['id' => $id, 'kind' => $d['kind'], 'today' => $this->tapCounts($ctx)]);
    }

    public function taps(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $rows = Database::all('SELECT id, sender_id, kind, created_at FROM love_taps WHERE couple_id = ? ORDER BY id DESC LIMIT 50', [$ctx->id()]);
        return [
            'recent' => array_map(fn($r) => ['id' => (int)$r['id'], 'from_me' => (int)$r['sender_id'] === $ctx->userId, 'kind' => $r['kind'], 'at' => Present::iso($r['created_at'])], $rows),
            'today' => $this->tapCounts($ctx),
        ];
    }

    // ---- moods ---------------------------------------------------------

    public function setMood(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $d = Validator::check($req->json(), ['mood' => 'required,in:happy|loved|excited|calm|tired|stressed|sad|missing_you|angry', 'note' => 'string,max:200']);
        Database::run(
            'INSERT INTO moods (couple_id, user_id, day, mood, note) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE mood = VALUES(mood), note = VALUES(note)',
            [$ctx->id(), $ctx->userId, GameController::today(), $d['mood'], $d['note'] ?? null]
        );
        Events::emit($ctx->id(), $ctx->userId, 'mood.updated', ['mood' => $d['mood']]);
        return $this->moods($req, $user);
    }

    public function moods(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $rows = Database::all('SELECT user_id, day, mood, note FROM moods WHERE couple_id = ? ORDER BY day DESC LIMIT 60', [$ctx->id()]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['day']]['day'] = $r['day'];
            $out[$r['day']][(int)$r['user_id'] === $ctx->userId ? 'mine' : 'partner'] = ['mood' => $r['mood'], 'note' => $r['note']];
        }
        return array_values($out);
    }

    // ---- notes: journal, letters, shared notes, wishes ------------------

    public function notes(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $kind = $req->query['kind'] ?? null;
        $sql = 'SELECT * FROM notes WHERE couple_id = ? AND (author_id = ? OR deliver_at IS NULL OR deliver_at <= UTC_TIMESTAMP())';
        $params = [$ctx->id(), $ctx->userId];
        if (in_array($kind, ['journal', 'letter', 'note', 'wish'], true)) {
            $sql .= ' AND kind = ?';
            $params[] = $kind;
        }
        // Scheduled letters you are waiting for show as sealed, with no content
        $sealed = Database::value('SELECT COUNT(*) FROM notes WHERE couple_id = ? AND author_id <> ? AND deliver_at > UTC_TIMESTAMP()', [$ctx->id(), $ctx->userId]);
        return [
            'notes' => array_map(fn($n) => $this->presentNote($n, $ctx), Database::all($sql . ' ORDER BY id DESC LIMIT 200', $params)),
            'sealed_letters_waiting' => (int)$sealed,
        ];
    }

    public function createNote(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('note:user:' . $ctx->userId, 100, 3600);
        $d = Validator::check($req->json(), [
            'kind' => 'required,in:journal|letter|note|wish',
            'title' => 'string,max:150',
            'body' => 'required,string,min:1,max:20000',
            'deliver_at' => 'string,max:40',
        ]);
        $deliver = isset($d['deliver_at']) ? $this->dt($d['deliver_at'], 'deliver_at') : null;
        if ($deliver !== null && $d['kind'] !== 'letter') {
            throw ApiException::validation(['deliver_at' => 'Only letters can be scheduled.']);
        }
        $id = Database::insert(
            'INSERT INTO notes (couple_id, author_id, kind, title, body, deliver_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$ctx->id(), $ctx->userId, $d['kind'], $d['title'] ?? null, $d['body'], $deliver]
        );
        // Scheduled letters only announce themselves when delivered (cron emits then)
        if ($deliver === null) {
            Events::emit($ctx->id(), $ctx->userId, 'note.created', ['id' => $id, 'kind' => $d['kind']]);
        }
        return Router::status(201, $this->presentNote(Database::one('SELECT * FROM notes WHERE id = ?', [$id]), $ctx));
    }

    public function openNote(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $n = Database::one('SELECT * FROM notes WHERE id = ? AND couple_id = ? AND (author_id = ? OR deliver_at IS NULL OR deliver_at <= UTC_TIMESTAMP())', [(int)$req->params['id'], $ctx->id(), $ctx->userId]);
        if (!$n) {
            throw new ApiException(404, 'not_found', 'Note not found.');
        }
        if ((int)$n['author_id'] !== $ctx->userId && $n['opened_at'] === null) {
            Database::run('UPDATE notes SET opened_at = UTC_TIMESTAMP() WHERE id = ?', [$n['id']]);
            Events::emit($ctx->id(), $ctx->userId, 'note.opened', ['id' => (int)$n['id']]);
            $n['opened_at'] = gmdate('Y-m-d H:i:s');
        }
        return $this->presentNote($n, $ctx);
    }

    public function updateNote(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $n = Database::one('SELECT * FROM notes WHERE id = ? AND couple_id = ? AND author_id = ?', [(int)$req->params['id'], $ctx->id(), $ctx->userId]);
        if (!$n) {
            throw new ApiException(404, 'not_found', 'Note not found.');
        }
        $d = Validator::check($req->json(), ['title' => 'string,max:150', 'body' => 'string,min:1,max:20000']);
        if ($d) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
            Database::run("UPDATE notes SET $sets WHERE id = ?", [...array_values($d), $n['id']]);
        }
        return $this->presentNote(Database::one('SELECT * FROM notes WHERE id = ?', [$n['id']]), $ctx);
    }

    public function deleteNote(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $n = Database::run('DELETE FROM notes WHERE id = ? AND couple_id = ? AND author_id = ?', [(int)$req->params['id'], $ctx->id(), $ctx->userId])->rowCount();
        if (!$n) {
            throw new ApiException(404, 'not_found', 'Note not found.');
        }
        return ['deleted' => true];
    }

    // ---- home dashboard ------------------------------------------------

    public function home(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $c = $ctx->couple;
        $partner = Database::one('SELECT * FROM users WHERE id = ?', [$ctx->partnerId]);
        $start = $c['relationship_start'] ?? substr($c['paired_at'], 0, 10);
        $today = GameController::today();
        $todayDt = new \DateTimeImmutable($today);
        $daysTogether = (new \DateTimeImmutable($start))->diff($todayDt)->days;

        $nextCountdown = Database::one('SELECT * FROM countdowns WHERE couple_id = ? AND target_at > UTC_TIMESTAMP() ORDER BY target_at LIMIT 1', [$ctx->id()]);
        $upcoming = Database::all(
            'SELECT * FROM calendar_events WHERE couple_id = ? AND (starts_at BETWEEN UTC_TIMESTAMP() AND UTC_TIMESTAMP() + INTERVAL 30 DAY OR repeat_yearly = 1) ORDER BY starts_at LIMIT 50',
            [$ctx->id()]
        );
        $events = [];
        foreach ($upcoming as $e) {
            $next = $this->nextOccurrence($e, $todayDt);
            if ($next !== null && $next <= $todayDt->modify('+30 days')->format('Y-m-d')) {
                $events[] = $this->present($e, self::RESOURCES['calendar']) + ['next_date' => $next];
            }
        }
        usort($events, fn($a, $b) => strcmp($a['next_date'], $b['next_date']));

        $anniv = null;
        if ($c['anniversary_date']) {
            $anniv = $this->nextOccurrence(['starts_at' => $c['anniversary_date'] . ' 00:00:00', 'repeat_yearly' => 1], $todayDt);
        }
        $birthday = $this->nextOccurrence(['starts_at' => $partner['dob'] . ' 00:00:00', 'repeat_yearly' => 1], $todayDt);
        $memory = Database::one(
            "SELECT * FROM memories WHERE couple_id = ? AND DATE_FORMAT(happened_on, '%m-%d') = ? AND happened_on < ? ORDER BY happened_on LIMIT 1",
            [$ctx->id(), substr($today, 5), $today]
        );
        $moods = Database::all('SELECT user_id, mood FROM moods WHERE couple_id = ? AND day = ?', [$ctx->id(), $today]);
        $moodOf = fn($uid) => ($m = array_values(array_filter($moods, fn($x) => (int)$x['user_id'] === $uid))) ? $m[0]['mood'] : null;
        $lastTap = Database::one('SELECT kind, created_at FROM love_taps WHERE couple_id = ? AND sender_id = ? ORDER BY id DESC LIMIT 1', [$ctx->id(), $ctx->partnerId]);
        $invites = (int)Database::value("SELECT COUNT(*) FROM game_sessions WHERE couple_id = ? AND status = 'invited' AND started_by <> ?", [$ctx->id(), $ctx->userId]);
        $daily = Database::all('SELECT user_id FROM daily_answers WHERE couple_id = ? AND day = ?', [$ctx->id(), $today]);
        $answered = array_map(fn($r) => (int)$r['user_id'], $daily);

        $milestones = [];
        foreach ([100, 200, 365, 500, 730, 1000, 1095, 1500, 1825, 2000, 2500, 3000, 3650] as $m) {
            if ($m > $daysTogether) {
                $milestones = ['days' => $m, 'in_days' => $m - $daysTogether, 'date' => (new \DateTimeImmutable($start))->modify("+$m days")->format('Y-m-d')];
                break;
            }
        }

        return [
            'partner' => Present::partner($partner) + ['mood_today' => $moodOf($ctx->partnerId)],
            'my_mood_today' => $moodOf($ctx->userId),
            'days_together' => $daysTogether,
            'relationship_start' => $start,
            'next_milestone' => $milestones ?: null,
            'next_meeting_at' => Present::iso($c['next_meeting_at']),
            'next_countdown' => $nextCountdown ? $this->present($nextCountdown, self::RESOURCES['countdowns']) : null,
            'anniversary' => $anniv ? ['date' => $anniv, 'in_days' => $todayDt->diff(new \DateTimeImmutable($anniv))->days] : null,
            'partner_birthday' => $birthday ? ['date' => $birthday, 'in_days' => $todayDt->diff(new \DateTimeImmutable($birthday))->days] : null,
            'upcoming' => array_slice($events, 0, 5),
            'memory_of_the_day' => $memory ? $this->present($memory, self::RESOURCES['memories']) : null,
            'daily_question' => ['i_answered' => in_array($ctx->userId, $answered, true), 'partner_answered' => in_array($ctx->partnerId, $answered, true)],
            'streak' => GameController::streak($ctx->id()),
            'game_invites' => $invites,
            'last_tap_from_partner' => $lastTap ? ['kind' => $lastTap['kind'], 'at' => Present::iso($lastTap['created_at'])] : null,
            'taps_today' => $this->tapCounts($ctx),
            'unread_messages' => MessageController::unreadCount($ctx),
            'bucket' => Database::one('SELECT COUNT(*) AS total, SUM(done_at IS NOT NULL) AS done FROM bucket_items WHERE couple_id = ?', [$ctx->id()]),
        ];
    }

    // ---- helpers -------------------------------------------------------

    private function resource(Request $req, array $user): array
    {
        $cfg = self::RESOURCES[$req->params['slug']] ?? null;
        if ($cfg === null) {
            throw new ApiException(404, 'not_found', 'Endpoint not found.');
        }
        return [CoupleContext::require($user), $cfg];
    }

    private function find(array $cfg, CoupleContext $ctx, int $id): array
    {
        $row = Database::one("SELECT * FROM {$cfg['table']} WHERE id = ? AND couple_id = ?", [$id, $ctx->id()]);
        if (!$row) {
            throw new ApiException(404, 'not_found', 'Item not found.');
        }
        return $row;
    }

    private function clean(Request $req, array $cfg, bool $creating, CoupleContext $ctx): array
    {
        $rules = $cfg['fields'];
        if (!$creating) {
            $rules = array_map(fn($r) => str_replace('required,', '', $r), $rules);
        }
        $d = Validator::check($req->json(), $rules);
        foreach ($cfg['dt'] as $f) {
            if (isset($d[$f])) {
                $d[$f] = $this->dt($d[$f], $f);
            }
        }
        foreach ($cfg['bool'] as $f) {
            if (isset($d[$f])) {
                $d[$f] = (int)$d[$f];
            }
        }
        if (isset($d['media_id']) && !Database::value('SELECT 1 FROM media WHERE id = ? AND couple_id = ? AND deleted_at IS NULL', [$d['media_id'], $ctx->id()])) {
            throw ApiException::validation(['media_id' => 'Upload the photo first.']);
        }
        return $d;
    }

    private function dt(string $value, string $field): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            throw ApiException::validation([$field => 'Use an ISO 8601 date and time.']);
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private function present(array $r, array $cfg): array
    {
        $out = ['id' => (int)$r['id']];
        foreach (array_keys($cfg['fields']) as $f) {
            $v = $r[$f] ?? null;
            $out[$f] = match (true) {
                in_array($f, $cfg['dt'], true) => Present::iso($v),
                in_array($f, $cfg['bool'], true) => (bool)$v,
                $f === 'media_id' || $f === 'remind_minutes' => $v !== null ? (int)$v : null,
                default => $v,
            };
        }
        if (isset($r['media_id'])) {
            $out['media_url'] = Present::mediaUrl($r['media_id'] ? (int)$r['media_id'] : null);
        }
        if (array_key_exists('done_at', $r)) {
            $out['done'] = $r['done_at'] !== null;
            $out['done_at'] = Present::iso($r['done_at']);
            $out['done_by'] = $r['done_by'] !== null ? (int)$r['done_by'] : null;
        }
        $out['created_by'] = (int)$r[$cfg['owner']];
        $out['created_at'] = Present::iso($r['created_at']);
        return $out;
    }

    private function presentNote(array $n, CoupleContext $ctx): array
    {
        return [
            'id' => (int)$n['id'],
            'kind' => $n['kind'],
            'title' => $n['title'],
            'body' => $n['body'],
            'from_me' => (int)$n['author_id'] === $ctx->userId,
            'deliver_at' => Present::iso($n['deliver_at']),
            'opened' => $n['opened_at'] !== null,
            'created_at' => Present::iso($n['created_at']),
            'updated_at' => Present::iso($n['updated_at']),
        ];
    }

    private function tapCounts(CoupleContext $ctx): array
    {
        $rows = Database::all(
            'SELECT sender_id = ? AS mine, kind, COUNT(*) AS n FROM love_taps WHERE couple_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR GROUP BY mine, kind',
            [$ctx->userId, $ctx->id()]
        );
        $out = ['sent' => [], 'received' => []];
        foreach ($rows as $r) {
            $out[$r['mine'] ? 'sent' : 'received'][$r['kind']] = (int)$r['n'];
        }
        return $out;
    }

    /** Next date (Y-m-d) an event happens on or after today; yearly events roll forward (Feb 29 → Feb 28). */
    private function nextOccurrence(array $e, \DateTimeImmutable $today): ?string
    {
        $d = new \DateTimeImmutable(substr($e['starts_at'], 0, 10));
        if (empty($e['repeat_yearly'])) {
            return $d >= $today ? $d->format('Y-m-d') : null;
        }
        [$m, $day] = [(int)$d->format('m'), (int)$d->format('d')];
        foreach ([(int)$today->format('Y'), (int)$today->format('Y') + 1] as $y) {
            $dd = ($m === 2 && $day === 29 && !checkdate(2, 29, $y)) ? 28 : $day;
            $cand = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $dd));
            if ($cand >= $today) {
                return $cand->format('Y-m-d');
            }
        }
        return null;
    }
}
