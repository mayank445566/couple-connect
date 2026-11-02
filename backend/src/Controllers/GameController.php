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
use App\Services\Entitlements;
use App\Services\Events;
use App\Services\Lang;
use App\Services\Present;

/**
 * Data-driven couple games. A game is a row with a mode; questions live in packs.
 * New games and packs are added from the admin panel without code changes.
 *
 * Modes:
 *  prompt - a question, truth/dare or challenge. Either the round's subject answers
 *           (Truth or Dare) or both answer (config.both_answer), then it is revealed.
 *  match  - both pick an option; revealed together; matching answers add a "sync" point.
 *  guess  - the subject answers about themself, the partner guesses. Options are scored
 *           automatically; free-text guesses are judged by the subject.
 *  quiz   - both answer a question that has a correct answer.
 *
 * Every round can be skipped and every session stopped by either partner, with no penalty.
 */
final class GameController
{
    private const ADULT_LEVELS = ['flirty', 'romantic', 'bold', 'intimate', 'spicy'];
    private const LEVELS = ['easy', 'medium', 'hard'];
    private const MAX_ROUNDS = 500;

    // ---- catalog -------------------------------------------------------

    public function index(Request $req, array $user): array
    {
        $ent = Entitlements::forUser($user);
        $games = Database::all('SELECT * FROM games WHERE enabled = 1 ORDER BY section, sort_order, id');
        $packs = Database::all(
            'SELECT p.id, p.game_id, p.title, p.title_hi, p.level, p.is_premium, p.couple_id, COUNT(q.id) AS questions
               FROM question_packs p LEFT JOIN questions q ON q.pack_id = p.id AND q.enabled = 1
              WHERE p.enabled = 1 AND (p.couple_id IS NULL OR p.couple_id = ?)
              GROUP BY p.id ORDER BY p.sort_order, p.id',
            [$user['active_couple_id'] ?? 0]
        );
        $byGame = [];
        foreach ($packs as $p) {
            $byGame[(int)$p['game_id']][] = [
                'id' => (int)$p['id'],
                'title' => Lang::pick($p, 'title'),
                'level' => $p['level'],
                'premium' => (bool)$p['is_premium'],
                'custom' => $p['couple_id'] !== null,
                'questions' => (int)$p['questions'],
                'locked' => (bool)$p['is_premium'] && !$ent['premium']['owned'],
            ];
        }
        $out = ['free' => [], 'adult' => []];
        foreach ($games as $g) {
            $out[$g['section']][] = $this->presentGame($g, $byGame[(int)$g['id']] ?? [], $ent['premium']['owned']);
        }
        return [
            'games' => $out,
            'premium' => $ent['premium']['owned'],
            'adult' => $this->adultStatus($user),
        ];
    }

    public function show(Request $req, array $user): array
    {
        $g = $this->game($req->params['slug']);
        $ent = Entitlements::forUser($user);
        $packs = Database::all(
            'SELECT p.*, (SELECT COUNT(*) FROM questions q WHERE q.pack_id = p.id AND q.enabled = 1) AS questions
               FROM question_packs p WHERE p.game_id = ? AND p.enabled = 1 AND (p.couple_id IS NULL OR p.couple_id = ?) ORDER BY p.sort_order, p.id',
            [$g['id'], $user['active_couple_id'] ?? 0]
        );
        return $this->presentGame($g, array_map(fn($p) => [
            'id' => (int)$p['id'], 'title' => Lang::pick($p, 'title'), 'level' => $p['level'], 'premium' => (bool)$p['is_premium'],
            'custom' => $p['couple_id'] !== null, 'questions' => (int)$p['questions'],
            'locked' => (bool)$p['is_premium'] && !$ent['premium']['owned'],
        ], $packs), $ent['premium']['owned']);
    }

    // ---- sessions ------------------------------------------------------

    public function start(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('game-start:user:' . $ctx->userId, 30, 3600);
        $d = Validator::check($req->json(), [
            'game' => 'required,string,max:60',
            'pack_id' => 'int',
            'level' => 'string,max:20',
            'rounds' => 'int',
        ]);
        $g = $this->game($d['game']);
        $this->assertGameAccess($g, $user, $ctx);
        $level = $d['level'] ?? null;
        if ($level !== null && !in_array($level, $g['section'] === 'adult' ? self::ADULT_LEVELS : self::LEVELS, true)) {
            throw ApiException::validation(['level' => 'Invalid level for this game.']);
        }
        if (isset($d['pack_id'])) {
            $pack = Database::one(
                'SELECT * FROM question_packs WHERE id = ? AND game_id = ? AND enabled = 1 AND (couple_id IS NULL OR couple_id = ?)',
                [$d['pack_id'], $g['id'], $ctx->id()]
            );
            if (!$pack) {
                throw ApiException::validation(['pack_id' => 'Pack not found.']);
            }
            if ($pack['is_premium'] && !Entitlements::has($user, 'premium')) {
                throw new ApiException(402, 'premium_required', 'Unlock Premium to play this pack.');
            }
        }
        // Premium couples have no round limit: 0 means endless (play until you stop or every card is used)
        $premium = Entitlements::has($user, 'premium');
        $rounds = $d['rounds'] ?? (int)$g['rounds'];
        $rounds = $premium ? ($rounds <= 0 ? 0 : min(self::MAX_ROUNDS, $rounds)) : max(1, min(30, $rounds));
        // One pending invite per game: a new invite replaces the old one
        Database::run(
            "UPDATE game_sessions SET status = 'stopped', stopped_by = ? WHERE couple_id = ? AND game_id = ? AND status = 'invited'",
            [$ctx->userId, $ctx->id(), $g['id']]
        );
        $id = Database::insert(
            'INSERT INTO game_sessions (couple_id, game_id, pack_id, started_by, level, total_rounds, scores) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$ctx->id(), $g['id'], $d['pack_id'] ?? null, $ctx->userId, $level, $rounds, json_encode([(string)$ctx->userId => 0, (string)$ctx->partnerId => 0, 'sync' => 0])]
        );
        // Adult game titles stay out of the event so nothing explicit can reach a notification
        Events::emit($ctx->id(), $ctx->userId, 'game.invited', ['session_id' => $id, 'game' => $g['section'] === 'adult' ? 'private' : $g['slug']]);
        return Router::status(201, $this->state($id, $ctx));
    }

    public function sessions(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $status = $req->query['status'] ?? null;
        $sql = 'SELECT s.*, g.slug, g.title, g.title_hi, g.section, g.mode FROM game_sessions s JOIN games g ON g.id = s.game_id WHERE s.couple_id = ?';
        $params = [$ctx->id()];
        if (in_array($status, ['invited', 'active', 'finished', 'stopped'], true)) {
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        }
        $rows = Database::all($sql . ' ORDER BY s.id DESC LIMIT 50', $params);
        return array_map(fn($s) => [
            'id' => (int)$s['id'],
            'game' => ['slug' => $s['slug'], 'title' => Lang::pick($s, 'title'), 'section' => $s['section'], 'mode' => $s['mode']],
            'status' => $s['status'],
            'started_by' => (int)$s['started_by'],
            'round_no' => (int)$s['round_no'],
            'total_rounds' => (int)$s['total_rounds'],
            'scores' => json_decode((string)$s['scores'], true),
            'created_at' => Present::iso($s['created_at']),
            'finished_at' => Present::iso($s['finished_at']),
        ], $rows);
    }

    public function showSession(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        return $this->state($this->session($ctx, (int)$req->params['id'])['id'], $ctx);
    }

    public function join(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->session($ctx, (int)$req->params['id']);
        if ($s['status'] !== 'invited') {
            throw new ApiException(409, 'not_invited', 'This game is no longer waiting for you.');
        }
        if ((int)$s['started_by'] === $ctx->userId) {
            throw new ApiException(409, 'own_invite', 'Waiting for your partner to join.');
        }
        $g = $this->gameById((int)$s['game_id']);
        $this->assertGameAccess($g, $user, $ctx);
        Database::run("UPDATE game_sessions SET status = 'active' WHERE id = ?", [$s['id']]);
        $this->newRound($s, $g, $ctx, null);
        Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        return $this->state((int)$s['id'], $ctx);
    }

    public function decline(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->session($ctx, (int)$req->params['id']);
        if ($s['status'] !== 'invited') {
            throw new ApiException(409, 'not_invited', 'This game is no longer waiting.');
        }
        Database::run("UPDATE game_sessions SET status = 'declined', stopped_by = ? WHERE id = ?", [$ctx->userId, $s['id']]);
        Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        return $this->state((int)$s['id'], $ctx);
    }

    /** Either partner may stop any game at any time. Always allowed, even without premium or consent. */
    public function stop(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->session($ctx, (int)$req->params['id']);
        if (in_array($s['status'], ['invited', 'active'], true)) {
            Database::run("UPDATE game_sessions SET status = 'stopped', stopped_by = ?, finished_at = UTC_TIMESTAMP() WHERE id = ?", [$ctx->userId, $s['id']]);
            Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        }
        return $this->state((int)$s['id'], $ctx);
    }

    /** Skip the current round. Always allowed; no score change. */
    public function skip(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->activeSession($ctx, (int)$req->params['id']);
        $round = $this->currentRound((int)$s['id']);
        if ($round && $round['status'] === 'open') {
            Database::run("UPDATE game_rounds SET status = 'skipped', revealed_at = UTC_TIMESTAMP(), result = ? WHERE id = ?", [json_encode(['skipped_by' => $ctx->userId]), $round['id']]);
            Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        }
        return $this->state((int)$s['id'], $ctx);
    }

    public function next(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->activeSession($ctx, (int)$req->params['id']);
        $g = $this->gameById((int)$s['game_id']);
        $this->assertGameAccess($g, $user, $ctx);
        $round = $this->currentRound((int)$s['id']);
        if ($round && $round['status'] === 'open') {
            throw new ApiException(409, 'round_open', 'Finish or skip this round first.');
        }
        $kind = $req->input('kind');
        if ($kind !== null && !in_array($kind, ['truth', 'dare'], true)) {
            throw ApiException::validation(['kind' => 'Choose truth or dare.']);
        }
        $endless = (int)$s['total_rounds'] === 0;
        if ((!$endless && (int)$s['round_no'] >= (int)$s['total_rounds']) || (int)$s['round_no'] >= 60000 || !$this->newRound($s, $g, $ctx, $kind)) {
            $this->finish((int)$s['id']);
        }
        Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        return $this->state((int)$s['id'], $ctx);
    }

    public function answer(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->activeSession($ctx, (int)$req->params['id']);
        $g = $this->gameById((int)$s['game_id']);
        $this->assertGameAccess($g, $user, $ctx);
        $round = $this->currentRound((int)$s['id']);
        if (!$round || $round['status'] !== 'open') {
            throw new ApiException(409, 'no_open_round', 'There is no question waiting for an answer.');
        }
        $q = Database::one('SELECT * FROM questions WHERE id = ?', [$round['question_id']]);
        $mode = $g['mode'];
        $config = json_decode((string)$g['config'], true) ?: [];
        $subject = $round['subject_id'] !== null ? (int)$round['subject_id'] : null;

        if ($mode === 'prompt' && empty($config['both_answer']) && $subject !== null && $subject !== $ctx->userId) {
            throw new ApiException(409, 'not_your_turn', "It's your partner's turn.");
        }
        if ($mode === 'guess' && $q['kind'] === 'two_truths' && $subject !== $ctx->userId
            && !Database::value('SELECT 1 FROM game_answers WHERE round_id = ? AND user_id = ?', [$round['id'], $subject])) {
            throw new ApiException(409, 'wait_for_partner', 'Wait for your partner to write their statements.');
        }

        $answer = $this->normalizeAnswer($req->input('answer'), $q, $ctx, $mode === 'guess' && $subject !== $ctx->userId);
        try {
            // Answer and reveal commit together, so a round can never be left half-revealed
            Database::transaction(function () use ($round, $ctx, $answer, $s, $g, $q) {
                Database::run('SELECT id FROM game_rounds WHERE id = ? FOR UPDATE', [$round['id']]);
                Database::run('INSERT INTO game_answers (round_id, user_id, answer) VALUES (?, ?, ?)', [$round['id'], $ctx->userId, json_encode($answer, JSON_UNESCAPED_UNICODE)]);
                $this->maybeReveal($s, $g, $round, $q, $ctx);
            });
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ApiException(409, 'already_answered', 'You already answered this one.');
            }
            throw $e;
        }
        Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        return $this->state((int)$s['id'], $ctx);
    }

    /** Guess mode with free text: the subject says whether the partner's guess was right. */
    public function judge(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $s = $this->activeSession($ctx, (int)$req->params['id']);
        $round = $this->currentRound((int)$s['id']);
        $result = $round ? (json_decode((string)$round['result'], true) ?: []) : [];
        if (!$round || $round['status'] !== 'revealed' || ($result['needs_judge'] ?? false) !== true) {
            throw new ApiException(409, 'nothing_to_judge', 'Nothing to judge right now.');
        }
        if ((int)$round['subject_id'] !== $ctx->userId) {
            throw new ApiException(403, 'not_subject', 'Only the person the question was about can judge.');
        }
        $correct = (bool)$req->input('correct', false);
        $result['needs_judge'] = false;
        $result['correct'] = $correct;
        Database::transaction(function () use ($round, $result, $correct, $s, $ctx) {
            Database::run('UPDATE game_rounds SET result = ? WHERE id = ?', [json_encode($result), $round['id']]);
            if ($correct) {
                $this->addScore((int)$s['id'], (string)$ctx->partnerId, 1);
            }
        });
        Events::emit($ctx->id(), $ctx->userId, 'game.updated', ['session_id' => (int)$s['id']]);
        return $this->state((int)$s['id'], $ctx);
    }

    // ---- custom questions ----------------------------------------------

    public function addCustom(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('custom-q:user:' . $ctx->userId, 100, 3600);
        $g = $this->game($req->params['slug']);
        $this->assertGameAccess($g, $user, $ctx);
        $d = Validator::check($req->json(), ['text' => 'required,string,min:3,max:500', 'kind' => 'in:question|truth|dare|challenge|statement']);
        $options = $req->input('options');
        if ($options !== null) {
            if (!is_array($options) || count($options) < 2 || count($options) > 6) {
                throw ApiException::validation(['options' => 'Give 2 to 6 options.']);
            }
            $options = array_values(array_map(fn($o) => mb_substr(trim((string)$o), 0, 120), $options));
        } elseif (in_array($g['mode'], ['match', 'quiz'], true)) {
            throw ApiException::validation(['options' => 'This game needs answer options.']);
        }
        $packId = Database::value('SELECT id FROM question_packs WHERE game_id = ? AND couple_id = ?', [$g['id'], $ctx->id()]);
        if (!$packId) {
            $packId = Database::insert(
                'INSERT INTO question_packs (game_id, couple_id, title, level, is_premium, sort_order) VALUES (?, ?, ?, ?, 0, 999)',
                [$g['id'], $ctx->id(), 'Our own questions', $g['section'] === 'adult' ? 'romantic' : 'easy']
            );
        }
        $id = Database::insert(
            'INSERT INTO questions (pack_id, kind, text, options, created_by) VALUES (?, ?, ?, ?, ?)',
            [$packId, $d['kind'] ?? ($g['slug'] === 'truth-or-dare' ? 'truth' : 'question'), $d['text'], $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, $ctx->userId]
        );
        return Router::status(201, ['id' => $id, 'pack_id' => (int)$packId, 'text' => $d['text'], 'options' => $options]);
    }

    public function listCustom(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $g = $this->game($req->params['slug']);
        $rows = Database::all(
            'SELECT q.id, q.kind, q.text, q.options, q.created_by FROM questions q JOIN question_packs p ON p.id = q.pack_id
              WHERE p.game_id = ? AND p.couple_id = ? ORDER BY q.id DESC',
            [$g['id'], $ctx->id()]
        );
        return array_map(fn($r) => ['id' => (int)$r['id'], 'kind' => $r['kind'], 'text' => $r['text'], 'options' => json_decode((string)$r['options'], true), 'created_by' => (int)$r['created_by']], $rows);
    }

    public function deleteCustom(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $n = Database::run(
            'DELETE q FROM questions q JOIN question_packs p ON p.id = q.pack_id WHERE q.id = ? AND p.couple_id = ?
               AND NOT EXISTS (SELECT 1 FROM game_rounds r WHERE r.question_id = q.id)',
            [(int)$req->params['id'], $ctx->id()]
        )->rowCount();
        if ($n === 0) {
            // Already used in a game: disable instead of deleting, so history stays readable
            $n = Database::run('UPDATE questions q JOIN question_packs p ON p.id = q.pack_id SET q.enabled = 0 WHERE q.id = ? AND p.couple_id = ?', [(int)$req->params['id'], $ctx->id()])->rowCount();
        }
        if ($n === 0) {
            throw new ApiException(404, 'not_found', 'Question not found.');
        }
        return ['deleted' => true];
    }

    // ---- 18+ consent ---------------------------------------------------

    /** Each partner opts in (or out) independently. Opting out stops any running 18+ game. */
    public function adultConsent(Request $req, array $user): array
    {
        $optIn = (bool)$req->input('opt_in', false);
        if ($optIn) {
            if (Validator::age($user['dob']) < 18) {
                throw new ApiException(403, 'adult_age', 'You must be 18 or older.');
            }
            if ($req->input('confirm_adult') !== true) {
                throw ApiException::validation(['confirm_adult' => 'Confirm that you are 18+ and choose to see adult content.']);
            }
            Database::run('UPDATE users SET adult_opt_in_at = UTC_TIMESTAMP(), adult_age_confirmed_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        } else {
            Database::run('UPDATE users SET adult_opt_in_at = NULL WHERE id = ?', [$user['id']]);
            if ($user['active_couple_id'] !== null) {
                $stopped = Database::run(
                    "UPDATE game_sessions s JOIN games g ON g.id = s.game_id SET s.status = 'stopped', s.stopped_by = ?, s.finished_at = UTC_TIMESTAMP()
                      WHERE s.couple_id = ? AND g.section = 'adult' AND s.status IN ('invited','active')",
                    [$user['id'], $user['active_couple_id']]
                )->rowCount();
                if ($stopped) {
                    Events::emit((int)$user['active_couple_id'], (int)$user['id'], 'game.updated', ['stopped_adult' => true]);
                }
            }
        }
        $user['adult_opt_in_at'] = $optIn ? gmdate('Y-m-d H:i:s') : null;
        return $this->adultStatus($user);
    }

    // ---- daily question ------------------------------------------------

    public function daily(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        return $this->dailyState($ctx);
    }

    public function answerDaily(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $d = Validator::check($req->json(), ['answer' => 'required,string,min:1,max:1000']);
        $state = $this->dailyState($ctx);
        if ($state['question'] === null) {
            throw new ApiException(404, 'no_question', 'No daily question is configured.');
        }
        Database::run(
            'INSERT INTO daily_answers (couple_id, day, user_id, question_id, answer) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE answer = VALUES(answer)',
            [$ctx->id(), $state['day'], $ctx->userId, $state['question']['id'], $d['answer']]
        );
        Events::emit($ctx->id(), $ctx->userId, 'daily.answered', ['day' => $state['day']]);
        return $this->dailyState($ctx);
    }

    public function dailyHistory(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        $rows = Database::all(
            'SELECT a.day, a.user_id, a.answer, q.text, q.text_hi FROM daily_answers a JOIN questions q ON q.id = a.question_id
              WHERE a.couple_id = ? AND a.day < ? ORDER BY a.day DESC LIMIT 120',
            [$ctx->id(), self::today()]
        );
        $days = [];
        foreach ($rows as $r) {
            $days[$r['day']]['day'] = $r['day'];
            $days[$r['day']]['question'] = Lang::pick($r, 'text');
            $days[$r['day']]['answers'][(int)$r['user_id'] === $ctx->userId ? 'mine' : 'partner'] = $r['answer'];
        }
        // Past answers are shown only for days you answered too
        return array_values(array_filter($days, fn($d) => isset($d['answers']['mine'])));
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(\App\Core\Config::get('APP_TIMEZONE', 'Asia/Kolkata'))))->format('Y-m-d');
    }

    public static function streak(int $coupleId): int
    {
        $days = Database::run(
            'SELECT day FROM daily_answers WHERE couple_id = ? GROUP BY day HAVING COUNT(DISTINCT user_id) = 2 ORDER BY day DESC LIMIT 400',
            [$coupleId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $set = array_flip($days);
        $d = new \DateTimeImmutable(self::today());
        if (!isset($set[$d->format('Y-m-d')])) {
            $d = $d->modify('-1 day'); // today not finished yet: the streak is still alive
        }
        $n = 0;
        while (isset($set[$d->format('Y-m-d')])) {
            $n++;
            $d = $d->modify('-1 day');
        }
        return $n;
    }

    private function dailyState(CoupleContext $ctx): array
    {
        $day = self::today();
        $answers = Database::all('SELECT user_id, question_id, answer FROM daily_answers WHERE couple_id = ? AND day = ?', [$ctx->id(), $day]);
        $qid = $answers[0]['question_id'] ?? null;
        if ($qid === null) {
            // Same question for both partners today (seeded by couple and day), never one this couple
            // already answered until the whole pack has been used.
            $qid = Database::value(
                'SELECT q.id FROM questions q JOIN question_packs p ON p.id = q.pack_id
                   LEFT JOIN (SELECT question_id, MAX(day) AS last_day FROM daily_answers WHERE couple_id = ? GROUP BY question_id) seen ON seen.question_id = q.id
                  WHERE p.slug = ? AND q.enabled = 1 AND p.enabled = 1
                  ORDER BY seen.last_day IS NOT NULL, seen.last_day, CRC32(CONCAT(?, \'-\', q.id, \'-\', ?)) LIMIT 1',
                [$ctx->id(), \App\Services\Settings::get('daily_question_pack'), $ctx->id(), $day]
            );
            $qid = $qid !== null && $qid !== false ? (int)$qid : null;
        }
        $q = $qid ? Database::one('SELECT id, text, text_hi FROM questions WHERE id = ?', [$qid]) : null;
        $mine = $partner = null;
        foreach ($answers as $a) {
            if ((int)$a['user_id'] === $ctx->userId) {
                $mine = $a['answer'];
            } else {
                $partner = $a['answer'];
            }
        }
        return [
            'day' => $day,
            'question' => $q ? ['id' => (int)$q['id'], 'text' => Lang::pick($q, 'text')] : null,
            'my_answer' => $mine,
            'partner_answered' => $partner !== null,
            // You see your partner's answer only after giving your own
            'partner_answer' => $mine !== null ? $partner : null,
            'streak' => self::streak($ctx->id()),
        ];
    }

    // ---- engine internals ----------------------------------------------

    private function newRound(array $s, array $g, CoupleContext $ctx, ?string $kind): bool
    {
        $config = json_decode((string)$g['config'], true) ?: [];
        $roundNo = (int)Database::value('SELECT round_no FROM game_sessions WHERE id = ?', [$s['id']]) + 1;
        $q = $this->pickQuestion($s, $g, $ctx, $kind);
        if ($q === null) {
            return false;
        }
        $subject = null;
        if ($g['mode'] === 'guess' || ($g['mode'] === 'prompt' && empty($config['both_answer']))) {
            // Turns alternate, starting with the partner who accepted the invite
            $starter = (int)$s['started_by'];
            $other = $starter === $ctx->userId ? $ctx->partnerId : $ctx->userId;
            $subject = $roundNo % 2 === 1 ? $other : $starter;
        }
        Database::transaction(function () use ($s, $roundNo, $q, $subject) {
            Database::insert('INSERT INTO game_rounds (session_id, round_no, question_id, subject_id) VALUES (?, ?, ?, ?)', [$s['id'], $roundNo, $q['id'], $subject]);
            Database::run('UPDATE game_sessions SET round_no = ? WHERE id = ?', [$roundNo, $s['id']]);
        });
        return true;
    }

    private function pickQuestion(array $s, array $g, CoupleContext $ctx, ?string $kind): ?array
    {
        $premium = Entitlements::has(Database::one('SELECT * FROM users WHERE id = ?', [$ctx->userId]), 'premium');
        $where = ['q.enabled = 1', 'p.enabled = 1', 'p.game_id = ?', '(p.couple_id IS NULL OR p.couple_id = ?)'];
        $params = [$g['id'], $ctx->id()];
        if ($s['pack_id']) {
            $where[] = 'p.id = ?';
            $params[] = $s['pack_id'];
        } elseif ($s['level']) {
            $where[] = '(p.level = ? OR p.couple_id IS NOT NULL)';
            $params[] = $s['level'];
        }
        if (!$premium) {
            $where[] = 'p.is_premium = 0';
        }
        if ($kind !== null) {
            $where[] = 'q.kind = ?';
            $params[] = $kind;
        }
        $where[] = 'q.id NOT IN (SELECT question_id FROM game_rounds WHERE session_id = ?)';
        $params[] = $s['id'];
        // Shuffle without repeats: cards this couple has never had come first, in random order.
        // Only when every card has been played do repeats start, oldest first.
        array_unshift($params, $ctx->id());
        $sql = fn(array $w) => 'SELECT q.* FROM questions q JOIN question_packs p ON p.id = q.pack_id
               LEFT JOIN (SELECT r.question_id, MAX(r.id) AS last_round FROM game_rounds r JOIN game_sessions gs ON gs.id = r.session_id
                           WHERE gs.couple_id = ? GROUP BY r.question_id) seen ON seen.question_id = q.id
              WHERE ' . implode(' AND ', $w) . '
              ORDER BY seen.last_round IS NOT NULL, seen.last_round, RAND() LIMIT 1';
        $q = Database::one($sql($where), $params);
        if ($q === null && (int)$s['total_rounds'] === 0) {
            // Endless game and the whole deck is used: reshuffle, least recently played first
            array_pop($where);
            array_pop($params);
            $q = Database::one($sql($where), $params);
        }
        return $q;
    }

    private function normalizeAnswer(mixed $a, array $q, CoupleContext $ctx, bool $isGuesser): array
    {
        if (!is_array($a)) {
            throw ApiException::validation(['answer' => 'Answer is missing.']);
        }
        $options = json_decode((string)$q['options'], true);
        if ($q['kind'] === 'two_truths') {
            if ($isGuesser) {
                $pick = $a['pick'] ?? null;
                if (!is_int($pick) || $pick < 0 || $pick > 2) {
                    throw ApiException::validation(['answer' => 'Pick which statement is the lie.']);
                }
                return ['pick' => $pick];
            }
            $st = $a['statements'] ?? null;
            $lie = $a['lie'] ?? null;
            if (!is_array($st) || count($st) !== 3 || !is_int($lie) || $lie < 0 || $lie > 2) {
                throw ApiException::validation(['answer' => 'Write three statements and mark the lie.']);
            }
            return ['statements' => array_map(fn($x) => mb_substr(trim((string)$x), 0, 200), array_values($st)), 'lie' => $lie];
        }
        if ($options === ['@couple']) {
            $uid = $a['user_id'] ?? null;
            if (!in_array($uid, [$ctx->userId, $ctx->partnerId], true)) {
                throw ApiException::validation(['answer' => 'Pick one of you.']);
            }
            return ['user_id' => $uid];
        }
        if (is_array($options) && $options) {
            $texts = array_map(fn($o) => is_array($o) ? (string)$o['t'] : (string)$o, $options);
            $choice = $a['choice'] ?? null;
            if (!is_string($choice) || !in_array($choice, $texts, true)) {
                throw ApiException::validation(['answer' => 'Choose one of the options.']);
            }
            return ['choice' => $choice];
        }
        if (isset($a['done']) && $a['done'] === true) {
            return ['done' => true];
        }
        $text = trim((string)($a['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            throw ApiException::validation(['answer' => 'Write an answer (up to 1000 characters).']);
        }
        return ['text' => $text];
    }

    private function maybeReveal(array $s, array $g, array $round, array $q, CoupleContext $ctx): void
    {
        $answers = [];
        foreach (Database::all('SELECT user_id, answer FROM game_answers WHERE round_id = ?', [$round['id']]) as $r) {
            $answers[(int)$r['user_id']] = json_decode($r['answer'], true);
        }
        $config = json_decode((string)$g['config'], true) ?: [];
        $subject = $round['subject_id'] !== null ? (int)$round['subject_id'] : null;
        $mode = $g['mode'];

        $ready = match (true) {
            $mode === 'prompt' && empty($config['both_answer']) => $subject !== null && isset($answers[$subject]),
            default => count($answers) === 2,
        };
        if (!$ready) {
            return;
        }
        $result = [];
        $scores = [];
        if ($mode === 'match') {
            $vals = array_values($answers);
            $result['match'] = $vals[0] == $vals[1];
            if ($result['match']) {
                $scores['sync'] = 1;
            }
        } elseif ($mode === 'quiz') {
            foreach ($answers as $uid => $a) {
                $ok = isset($a['choice']) && $q['correct'] !== null && $a['choice'] === $q['correct'];
                $result['correct'][$uid] = $ok;
                if ($ok) {
                    $scores[(string)$uid] = 1;
                }
            }
            $result['correct_answer'] = $q['correct'];
        } elseif ($mode === 'guess') {
            $guesser = $subject === $ctx->userId ? $ctx->partnerId : $ctx->userId;
            $truth = $answers[$subject] ?? [];
            $guess = $answers[$guesser] ?? [];
            if ($q['kind'] === 'two_truths') {
                $result['correct'] = ($guess['pick'] ?? -1) === ($truth['lie'] ?? -2);
            } elseif (isset($truth['text'])) {
                $result['needs_judge'] = true; // free text: the subject decides
            } else {
                $result['correct'] = $truth == $guess;
            }
            if (($result['correct'] ?? false) === true) {
                $scores[(string)$guesser] = 1;
            }
        }
        (function () use ($round, $result, $scores, $s) {
            Database::run("UPDATE game_rounds SET status = 'revealed', revealed_at = UTC_TIMESTAMP(), result = ? WHERE id = ?", [json_encode($result ?: new \stdClass()), $round['id']]);
            foreach ($scores as $k => $v) {
                $this->addScore((int)$s['id'], (string)$k, $v);
            }
        })();
    }

    private function addScore(int $sessionId, string $key, int $points): void
    {
        $scores = json_decode((string)Database::value('SELECT scores FROM game_sessions WHERE id = ? FOR UPDATE', [$sessionId]), true) ?: [];
        $scores[$key] = ($scores[$key] ?? 0) + $points;
        Database::run('UPDATE game_sessions SET scores = ? WHERE id = ?', [json_encode($scores), $sessionId]);
    }

    private function finish(int $sessionId): void
    {
        Database::run("UPDATE game_sessions SET status = 'finished', finished_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'active'", [$sessionId]);
    }

    /** Full session view for one partner. Hides the partner's answer until the round is revealed. */
    private function state(int $sessionId, CoupleContext $ctx): array
    {
        $s = Database::one('SELECT * FROM game_sessions WHERE id = ?', [$sessionId]);
        $g = $this->gameById((int)$s['game_id']);
        $round = $this->currentRound($sessionId);
        $current = null;
        if ($round) {
            $q = Database::one('SELECT * FROM questions WHERE id = ?', [$round['question_id']]);
            $answers = [];
            foreach (Database::all('SELECT user_id, answer FROM game_answers WHERE round_id = ?', [$round['id']]) as $r) {
                $answers[(int)$r['user_id']] = json_decode($r['answer'], true);
            }
            $revealed = $round['status'] !== 'open';
            $partnerAnswer = $answers[$ctx->partnerId] ?? null;
            $myAnswer = $answers[$ctx->userId] ?? null;
            $subject = $round['subject_id'] !== null ? (int)$round['subject_id'] : null;
            $visiblePartner = $revealed ? $partnerAnswer : null;
            // Two truths and a lie: the guesser sees the statements (not the lie) before guessing
            if (!$revealed && $q['kind'] === 'two_truths' && $subject === $ctx->partnerId && $partnerAnswer) {
                $visiblePartner = ['statements' => $partnerAnswer['statements']];
            }
            $options = json_decode((string)$q['options'], true);
            $current = [
                'id' => (int)$round['id'],
                'round_no' => (int)$round['round_no'],
                'status' => $round['status'],
                'question' => [
                    'id' => (int)$q['id'],
                    'kind' => $q['kind'],
                    'text' => Lang::pick($q, 'text'),
                    // "options" are the values the app sends back (same for both partners, whatever their language);
                    // "labels" are what to show.
                    'options' => is_array($options) ? array_map(fn($o) => is_array($o) ? $o['t'] : $o, $options) : null,
                    'labels' => $this->labels($q, $options),
                    'pick_partner' => $options === ['@couple'],
                    'level' => Database::value('SELECT level FROM question_packs WHERE id = ?', [$q['pack_id']]),
                ],
                'subject_id' => $subject,
                'your_turn' => $round['status'] === 'open' && $myAnswer === null,
                'my_answer' => $myAnswer,
                'partner_answered' => $partnerAnswer !== null,
                'partner_answer' => $visiblePartner,
                'result' => json_decode((string)$round['result'], true),
            ];
        }
        $out = [
            'id' => (int)$s['id'],
            'game' => ['slug' => $g['slug'], 'title' => Lang::pick($g, 'title'), 'section' => $g['section'], 'mode' => $g['mode'], 'wild' => !empty((json_decode((string)$g['config'], true) ?: [])['wild'])],
            'status' => $s['status'],
            'started_by' => (int)$s['started_by'],
            'level' => $s['level'],
            'endless' => (int)$s['total_rounds'] === 0,
            'round_no' => (int)$s['round_no'],
            'total_rounds' => (int)$s['total_rounds'],
            'scores' => json_decode((string)$s['scores'], true),
            'round' => $current,
            'can_skip' => $s['status'] === 'active',
            'can_stop' => in_array($s['status'], ['invited', 'active'], true),
        ];
        if ($s['status'] === 'finished') {
            $out['summary'] = $this->summary($s, $g);
        }
        return $out;
    }

    private function summary(array $s, array $g): array
    {
        $rounds = Database::all("SELECT r.status, r.result, r.question_id FROM game_rounds r WHERE r.session_id = ?", [$s['id']]);
        $played = count(array_filter($rounds, fn($r) => $r['status'] === 'revealed'));
        $sum = ['rounds_played' => $played, 'rounds_skipped' => count($rounds) - $played];
        if ($g['mode'] === 'match' && $played > 0) {
            $scores = json_decode((string)$s['scores'], true) ?: [];
            $sum['compatibility_percent'] = (int)round(100 * ($scores['sync'] ?? 0) / $played);
        }
        // Category tally for quizzes like Love Language (options carry a "cat")
        $cats = [];
        foreach (Database::all(
            'SELECT a.user_id, a.answer, q.options FROM game_answers a JOIN game_rounds r ON r.id = a.round_id JOIN questions q ON q.id = r.question_id WHERE r.session_id = ?',
            [$s['id']]
        ) as $a) {
            $opts = json_decode((string)$a['options'], true);
            $choice = json_decode($a['answer'], true)['choice'] ?? null;
            foreach ((array)$opts as $o) {
                if (is_array($o) && isset($o['cat']) && $o['t'] === $choice) {
                    $cats[(int)$a['user_id']][$o['cat']] = ($cats[(int)$a['user_id']][$o['cat']] ?? 0) + 1;
                }
            }
        }
        if ($cats) {
            foreach ($cats as $uid => $c) {
                arsort($c);
                $sum['top_category'][$uid] = array_key_first($c);
                $sum['categories'][$uid] = $c;
            }
        }
        return $sum;
    }

    private function adultStatus(array $user): array
    {
        $partnerOptIn = null;
        if ($user['active_couple_id'] !== null) {
            $partnerOptIn = (bool)Database::value(
                "SELECT u.adult_opt_in_at IS NOT NULL FROM couples c JOIN users u ON u.id = IF(c.user_a_id = ?, c.user_b_id, c.user_a_id) WHERE c.id = ? AND c.status = 'active'",
                [$user['id'], $user['active_couple_id']]
            );
        }
        $eligible = Validator::age($user['dob']) >= 18;
        return [
            'available' => \App\Services\Settings::bool('adult_section_enabled') && $eligible,
            'my_opt_in' => $user['adult_opt_in_at'] !== null,
            'partner_opt_in' => $partnerOptIn,
            'premium' => Entitlements::has($user, 'premium'),
        ];
    }

    /** Display labels for options in the app's language; null when they equal the values. */
    private function labels(array $q, mixed $options): ?array
    {
        if (!is_array($options) || $options === ['@couple']) {
            return null;
        }
        $hi = Lang::options($q);
        if ($hi === null || count($hi) !== count($options)) {
            return null;
        }
        return array_map(fn($o) => is_array($o) ? (string)($o['t'] ?? '') : (string)$o, $hi);
    }

    private function presentGame(array $g, array $packs, bool $premium): array
    {
        $config = json_decode((string)$g['config'], true) ?: [];
        return [
            'slug' => $g['slug'],
            'title' => Lang::pick($g, 'title'),
            'description' => Lang::pick($g, 'description'),
            'wild' => !empty($config['wild']),
            'section' => $g['section'],
            'mode' => $g['mode'],
            'icon' => $g['icon'],
            'premium' => (bool)$g['is_premium'] || $g['section'] === 'adult',
            'locked' => ((bool)$g['is_premium'] || $g['section'] === 'adult') && !$premium,
            'rounds' => (int)$g['rounds'],
            'both_answer' => !empty($config['both_answer']),
            'max_rounds' => $premium ? self::MAX_ROUNDS : 30,
            'choose_kind' => !empty($config['choose_kind']),
            'packs' => $packs,
        ];
    }

    private function assertGameAccess(array $g, array $user, CoupleContext $ctx): void
    {
        if (!$g['enabled']) {
            throw new ApiException(404, 'not_found', 'Game not found.');
        }
        if ($g['section'] === 'adult') {
            Entitlements::assertAdultAllowed($user, $ctx);
        } elseif ($g['is_premium'] && !Entitlements::has($user, 'premium')) {
            throw new ApiException(402, 'premium_required', 'Unlock Premium to play this game.');
        }
    }

    private function game(string $slug): array
    {
        $g = Database::one('SELECT * FROM games WHERE slug = ? AND enabled = 1', [$slug]);
        if (!$g) {
            throw new ApiException(404, 'not_found', 'Game not found.');
        }
        return $g;
    }

    private function gameById(int $id): array
    {
        return Database::one('SELECT * FROM games WHERE id = ?', [$id]);
    }

    private function session(CoupleContext $ctx, int $id): array
    {
        $s = Database::one('SELECT * FROM game_sessions WHERE id = ? AND couple_id = ?', [$id, $ctx->id()]);
        if (!$s) {
            throw new ApiException(404, 'not_found', 'Game not found.');
        }
        return $s;
    }

    private function activeSession(CoupleContext $ctx, int $id): array
    {
        $s = $this->session($ctx, $id);
        if ($s['status'] !== 'active') {
            throw new ApiException(409, 'not_active', 'This game is not running.');
        }
        return $s;
    }

    private function currentRound(int $sessionId): ?array
    {
        return Database::one('SELECT * FROM game_rounds WHERE session_id = ? ORDER BY round_no DESC LIMIT 1', [$sessionId]);
    }
}
