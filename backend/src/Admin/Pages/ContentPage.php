<?php
declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Audit;
use App\Admin\Ctx;
use App\Admin\HttpError;
use App\Admin\Redirect;
use App\Admin\View;
use App\Core\Database;

/**
 * Games, built-in question packs, questions and themes.
 * Couples' own custom packs (couple_id set) are private and never listed here.
 */
final class ContentPage
{
    private const KINDS = ['question', 'truth', 'dare', 'challenge', 'statement', 'two_truths'];
    private const LEVELS = ['easy', 'medium', 'romantic', 'flirty', 'bold', 'intimate', 'spicy'];

    public function games(Ctx $ctx): string
    {
        $games = Database::all(
            'SELECT g.*, (SELECT COUNT(*) FROM question_packs p WHERE p.game_id = g.id AND p.couple_id IS NULL) AS packs,
                    (SELECT COUNT(*) FROM questions q JOIN question_packs p ON p.id = q.pack_id WHERE p.game_id = g.id AND p.couple_id IS NULL AND q.enabled = 1) AS questions,
                    (SELECT COUNT(*) FROM game_sessions s WHERE s.game_id = g.id) AS plays
               FROM games g ORDER BY g.section, g.sort_order'
        );
        return View::render($ctx, 'Games', 'content/games', compact('games'));
    }

    public function game(Ctx $ctx): string
    {
        $g = $this->findGame($ctx->id());
        $packs = Database::all(
            'SELECT p.*, (SELECT COUNT(*) FROM questions q WHERE q.pack_id = p.id) AS total, (SELECT COUNT(*) FROM questions q WHERE q.pack_id = p.id AND q.enabled = 1) AS live
               FROM question_packs p WHERE p.game_id = ? AND p.couple_id IS NULL ORDER BY p.sort_order, p.id',
            [$g['id']]
        );
        return View::render($ctx, $g['title'], 'content/game', ['g' => $g, 'packs' => $packs, 'levels' => self::LEVELS]);
    }

    public function saveGame(Ctx $ctx): string
    {
        $g = $this->findGame($ctx->id());
        $title = mb_substr($ctx->post('title'), 0, 100);
        $desc = mb_substr($ctx->post('description'), 0, 400);
        $rounds = (int)$ctx->post('rounds');
        if ($title === '' || $desc === '' || $rounds < 1 || $rounds > 50) {
            throw new Redirect('/admin/games/' . $g['id'], 'Title, description and 1–50 rounds are required.', 'error');
        }
        // 18+ games are always premium; free games can optionally be premium
        $premium = $g['section'] === 'adult' ? 1 : ($ctx->checked('is_premium') ? 1 : 0);
        $data = ['title' => $title, 'description' => $desc, 'rounds' => $rounds, 'is_premium' => $premium, 'enabled' => $ctx->checked('enabled') ? 1 : 0, 'sort_order' => (int)$ctx->post('sort_order')];
        Database::run('UPDATE games SET title = ?, description = ?, rounds = ?, is_premium = ?, enabled = ?, sort_order = ? WHERE id = ?', [...array_values($data), $g['id']]);
        Audit::log($ctx, 'game_updated', 'game', (int)$g['id'], $this->diff($g, $data));
        throw new Redirect('/admin/games/' . $g['id'], 'Game saved.');
    }

    public function createPack(Ctx $ctx): string
    {
        $g = $this->findGame($ctx->id());
        $title = mb_substr($ctx->post('title'), 0, 100);
        $level = $ctx->post('level');
        if ($title === '' || !in_array($level, self::LEVELS, true)) {
            throw new Redirect('/admin/games/' . $g['id'], 'Give the pack a title and level.', 'error');
        }
        $premium = $g['section'] === 'adult' ? 1 : ($ctx->checked('is_premium') ? 1 : 0);
        $slug = $g['slug'] . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') . '-' . bin2hex(random_bytes(2));
        $id = Database::insert(
            'INSERT INTO question_packs (game_id, slug, title, level, is_premium, enabled, sort_order) VALUES (?, ?, ?, ?, ?, 0, (SELECT COALESCE(MAX(sort_order), 0) + 10 FROM question_packs p2 WHERE p2.game_id = ?))',
            [$g['id'], substr($slug, 0, 80), $title, $level, $premium, $g['id']]
        );
        Audit::log($ctx, 'pack_created', 'pack', $id, ['game' => (int)$g['id'], 'title' => $title]);
        throw new Redirect('/admin/packs/' . $id, 'Pack created. It stays hidden until you add questions and turn it on.');
    }

    public function pack(Ctx $ctx): string
    {
        $p = $this->findPack($ctx->id());
        $total = (int)Database::value('SELECT COUNT(*) FROM questions WHERE pack_id = ?', [$p['id']]);
        $off = ($ctx->page() - 1) * View::PER_PAGE;
        $questions = Database::all('SELECT * FROM questions WHERE pack_id = ? ORDER BY id LIMIT ' . View::PER_PAGE . " OFFSET $off", [$p['id']]);
        return View::render($ctx, $p['title'], 'content/pack', ['p' => $p, 'questions' => $questions, 'total' => $total, 'kinds' => self::KINDS, 'levels' => self::LEVELS]);
    }

    public function savePack(Ctx $ctx): string
    {
        $p = $this->findPack($ctx->id());
        $title = mb_substr($ctx->post('title'), 0, 100);
        $level = $ctx->post('level');
        if ($title === '' || !in_array($level, self::LEVELS, true)) {
            throw new Redirect('/admin/packs/' . $p['id'], 'Give the pack a title and level.', 'error');
        }
        $data = ['title' => $title, 'level' => $level, 'is_premium' => $p['section'] === 'adult' ? 1 : ($ctx->checked('is_premium') ? 1 : 0),
                 'enabled' => $ctx->checked('enabled') ? 1 : 0, 'sort_order' => (int)$ctx->post('sort_order')];
        Database::run('UPDATE question_packs SET title = ?, level = ?, is_premium = ?, enabled = ?, sort_order = ? WHERE id = ?', [...array_values($data), $p['id']]);
        Audit::log($ctx, 'pack_updated', 'pack', (int)$p['id'], $this->diff($p, $data));
        throw new Redirect('/admin/packs/' . $p['id'], 'Pack saved.');
    }

    public function addQuestion(Ctx $ctx): string
    {
        $p = $this->findPack($ctx->id());
        [$kind, $text, $options, $correct] = $this->questionInput($ctx, '/admin/packs/' . $p['id']);
        $id = Database::insert('INSERT INTO questions (pack_id, kind, text, options, correct) VALUES (?, ?, ?, ?, ?)', [$p['id'], $kind, $text, $options, $correct]);
        Audit::log($ctx, 'question_created', 'question', $id, ['pack' => (int)$p['id']]);
        throw new Redirect('/admin/packs/' . $p['id'], 'Question added.');
    }

    public function question(Ctx $ctx): string
    {
        $q = $this->findQuestion($ctx->id());
        return View::render($ctx, 'Edit question', 'content/question', ['q' => $q, 'kinds' => self::KINDS]);
    }

    public function saveQuestion(Ctx $ctx): string
    {
        $q = $this->findQuestion($ctx->id());
        [$kind, $text, $options, $correct] = $this->questionInput($ctx, '/admin/questions/' . $q['id']);
        $enabled = $ctx->checked('enabled') ? 1 : 0;
        Database::run('UPDATE questions SET kind = ?, text = ?, options = ?, correct = ?, enabled = ? WHERE id = ?', [$kind, $text, $options, $correct, $enabled, $q['id']]);
        Audit::log($ctx, 'question_updated', 'question', (int)$q['id'], $this->diff($q, ['kind' => $kind, 'text' => $text, 'options' => $options, 'correct' => $correct, 'enabled' => $enabled]));
        throw new Redirect('/admin/packs/' . $q['pack_id'], 'Question saved.');
    }

    public function themes(Ctx $ctx): string
    {
        $themes = Database::all('SELECT * FROM themes ORDER BY sort_order, id');
        return View::render($ctx, 'Themes', 'content/themes', compact('themes'));
    }

    public function theme(Ctx $ctx): string
    {
        $t = $ctx->id() ? Database::one('SELECT * FROM themes WHERE id = ?', [$ctx->id()]) : null;
        if ($ctx->id() && !$t) {
            throw new HttpError(404, 'Theme not found.');
        }
        $t ??= ['id' => 0, 'slug' => '', 'name' => '', 'is_premium' => 1, 'enabled' => 0, 'sort_order' => 100,
                'tokens' => json_encode(['primary' => '#E8456B', 'background' => '#FFF7F9', 'surface' => '#FFFFFF', 'text' => '#2A1B22', 'bubble_mine' => '#E8456B', 'bubble_theirs' => '#F3E6EA', 'dark' => false])];
        $t['tokens_pretty'] = json_encode(json_decode((string)$t['tokens'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return View::render($ctx, $t['id'] ? $t['name'] : 'New theme', 'content/theme', ['t' => $t]);
    }

    public function saveTheme(Ctx $ctx): string
    {
        $id = $ctx->id();
        $back = $id ? '/admin/themes/' . $id : '/admin/themes/new';
        $existing = $id ? Database::one('SELECT * FROM themes WHERE id = ?', [$id]) : null;
        if ($id && !$existing) {
            throw new HttpError(404, 'Theme not found.');
        }
        $name = mb_substr($ctx->post('name'), 0, 60);
        $tokens = json_decode($ctx->post('tokens'), true);
        $err = $name === '' ? 'Name is required.' : $this->tokenError($tokens);
        if ($err) {
            throw new Redirect($back, $err, 'error');
        }
        $json = json_encode($tokens, JSON_UNESCAPED_SLASHES);
        $vals = [$name, $ctx->checked('is_premium') ? 1 : 0, $json, $ctx->checked('enabled') ? 1 : 0, (int)$ctx->post('sort_order')];
        if ($existing) {
            Database::run('UPDATE themes SET name = ?, is_premium = ?, tokens = ?, enabled = ?, sort_order = ? WHERE id = ?', [...$vals, $id]);
            Audit::log($ctx, 'theme_updated', 'theme', $id, ['name' => $name]);
        } else {
            $slug = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ctx->post('slug') ?: $name)), '-'), 0, 40);
            if ($slug === '' || Database::value('SELECT 1 FROM themes WHERE slug = ?', [$slug])) {
                throw new Redirect($back, 'Choose a unique slug (letters, numbers and dashes).', 'error');
            }
            $id = Database::insert('INSERT INTO themes (slug, name, is_premium, tokens, enabled, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$slug, ...$vals]);
            Audit::log($ctx, 'theme_created', 'theme', $id, ['name' => $name, 'slug' => $slug]);
        }
        throw new Redirect('/admin/themes', 'Theme saved.');
    }

    /** Tokens the app reads; colours must be #hex or rgba() so nothing else can be injected into styles. */
    private function tokenError(mixed $t): ?string
    {
        if (!is_array($t) || array_is_list($t)) {
            return 'Tokens must be a JSON object.';
        }
        $color = '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\))$/';
        foreach (['primary', 'background', 'surface', 'text', 'bubble_mine', 'bubble_theirs'] as $k) {
            if (!isset($t[$k]) || !is_string($t[$k]) || !preg_match($color, $t[$k])) {
                return "\"$k\" must be a colour like #E8456B or rgba(255,255,255,0.5).";
            }
        }
        if (!isset($t['dark']) || !is_bool($t['dark'])) {
            return '"dark" must be true or false.';
        }
        if (isset($t['gradient']) && (!is_array($t['gradient']) || count($t['gradient']) < 2 || count($t['gradient']) > 4
            || array_filter($t['gradient'], fn($c) => !is_string($c) || !preg_match($color, $c)))) {
            return '"gradient" must be a list of 2–4 colours.';
        }
        if (isset($t['blur']) && (!is_int($t['blur']) || $t['blur'] < 0 || $t['blur'] > 40)) {
            return '"blur" must be a whole number from 0 to 40.';
        }
        $allowed = ['primary', 'background', 'surface', 'text', 'bubble_mine', 'bubble_theirs', 'dark', 'gradient', 'blur'];
        if ($extra = array_diff(array_keys($t), $allowed)) {
            return 'Unknown token(s): ' . implode(', ', $extra) . '.';
        }
        return null;
    }

    private function questionInput(Ctx $ctx, string $back): array
    {
        $kind = $ctx->post('kind');
        $text = mb_substr($ctx->post('text'), 0, 500);
        $optRaw = $ctx->post('options');
        $correct = $ctx->post('correct');
        if (!in_array($kind, self::KINDS, true) || $text === '') {
            throw new Redirect($back, 'Choose a kind and write the question.', 'error');
        }
        $options = null;
        if ($optRaw !== '') {
            $decoded = json_decode($optRaw, true);
            if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > 12) {
                throw new Redirect($back, 'Options must be a JSON list, e.g. ["Yes","No"] or ["@couple"].', 'error');
            }
            $options = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
        return [$kind, $text, $options, $correct !== '' ? mb_substr($correct, 0, 200) : null];
    }

    private function diff(array $before, array $after): array
    {
        $d = [];
        foreach ($after as $k => $v) {
            if ((string)($before[$k] ?? '') !== (string)$v) {
                $d[$k] = ['from' => is_string($before[$k] ?? null) ? mb_substr($before[$k], 0, 120) : ($before[$k] ?? null), 'to' => is_string($v) ? mb_substr($v, 0, 120) : $v];
            }
        }
        return $d;
    }

    private function findGame(int $id): array
    {
        $g = Database::one('SELECT * FROM games WHERE id = ?', [$id]);
        if (!$g) {
            throw new HttpError(404, 'Game not found.');
        }
        return $g;
    }

    private function findPack(int $id): array
    {
        $p = Database::one('SELECT p.*, g.title AS game_title, g.section, g.id AS game_id FROM question_packs p JOIN games g ON g.id = p.game_id WHERE p.id = ? AND p.couple_id IS NULL', [$id]);
        if (!$p) {
            throw new HttpError(404, 'Pack not found.');
        }
        return $p;
    }

    private function findQuestion(int $id): array
    {
        $q = Database::one('SELECT q.*, p.title AS pack_title FROM questions q JOIN question_packs p ON p.id = q.pack_id WHERE q.id = ? AND p.couple_id IS NULL', [$id]);
        if (!$q) {
            throw new HttpError(404, 'Question not found.');
        }
        return $q;
    }
}
