<?php
declare(strict_types=1);

namespace App\Setup;

use App\Core\Database;

/** Applies database/migrations/*.sql in order and loads starter content. Used by bin/ scripts and the web installer. */
final class Installer
{
    public static function migrationsDir(): string
    {
        $dir = getenv('MIGRATIONS_PATH') ?: dirname(BASE_PATH) . '/database/migrations';
        return is_dir($dir) ? $dir : BASE_PATH . '/database/migrations';
    }

    /** @return string[] versions applied now */
    public static function migrate(): array
    {
        $pdo = Database::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = array_flip($pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN));
        $files = glob(self::migrationsDir() . '/*.sql');
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (isset($done[$version])) {
                continue;
            }
            $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** Safe to re-run: existing rows (matched by slug / text) are updated, never duplicated. */
    public static function seed(): array
    {
        $file = dirname(BASE_PATH) . '/database/seeds/content.php';
        if (!is_file($file)) {
            $file = BASE_PATH . '/database/seeds/content.php';
        }
        $content = require $file;
        Database::transaction(function () use ($content) {
            $gSort = 0;
            foreach ($content['games'] as $g) {
                $gSort += 10;
                $section = $g['section'] ?? 'free';
                Database::run(
                    'INSERT INTO games (slug, title, description, section, mode, is_premium, rounds, icon, config, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), section = VALUES(section),
                       mode = VALUES(mode), is_premium = VALUES(is_premium), rounds = VALUES(rounds), icon = VALUES(icon), config = VALUES(config)',
                    [$g['slug'], $g['title'], $g['description'], $section, $g['mode'], $section === 'adult' ? 1 : (int)($g['premium'] ?? 0),
                     $g['rounds'], $g['icon'] ?? null, isset($g['config']) ? json_encode($g['config']) : null, $gSort]
                );
                $gameId = (int)Database::value('SELECT id FROM games WHERE slug = ?', [$g['slug']]);
                $pSort = 0;
                foreach ($g['packs'] as $p) {
                    $pSort += 10;
                    Database::run(
                        'INSERT INTO question_packs (game_id, slug, title, level, is_premium, sort_order) VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE title = VALUES(title), level = VALUES(level), is_premium = VALUES(is_premium)',
                        [$gameId, $p['slug'], $p['title'], $p['level'], $section === 'adult' ? 1 : (int)($p['premium'] ?? 0), $pSort]
                    );
                    $packId = (int)Database::value('SELECT id FROM question_packs WHERE slug = ?', [$p['slug']]);
                    foreach ($p['questions'] as $q) {
                        [$kind, $text] = $q;
                        $options = $q[2] ?? null;
                        $correct = $q[3] ?? null;
                        $opt = $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null;
                        $existing = Database::value(
                            "SELECT id FROM questions WHERE pack_id = ? AND text = ? AND ((options IS NULL AND ? IS NULL) OR JSON_EXTRACT(options, '$') = JSON_EXTRACT(?, '$'))",
                            [$packId, $text, $opt, $opt]
                        );
                        if ($existing) {
                            continue;
                        }
                        Database::run(
                            'INSERT INTO questions (pack_id, kind, text, options, correct) VALUES (?, ?, ?, ?, ?)',
                            [$packId, $kind, $text, $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, $correct]
                        );
                    }
                }
            }

            $tSort = 0;
            foreach ($content['themes'] as [$slug, $name, $premium, $tokens]) {
                $tSort += 10;
                Database::run(
                    'INSERT INTO themes (slug, name, is_premium, tokens, sort_order) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), is_premium = VALUES(is_premium), tokens = VALUES(tokens)',
                    [$slug, $name, $premium, json_encode($tokens), $tSort]
                );
            }

            foreach ($content['products'] as $p) {
                Database::run(
                    'INSERT INTO products (sku, title, description, price_paise, grants, play_product_id) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), grants = VALUES(grants), play_product_id = VALUES(play_product_id)',
                    [$p['sku'], $p['title'], $p['description'], $p['price_paise'], json_encode($p['grants']), $p['play_product_id']]
                );
            }
        });

        return Database::one('SELECT (SELECT COUNT(*) FROM games) g, (SELECT COUNT(*) FROM question_packs) p, (SELECT COUNT(*) FROM questions) q, (SELECT COUNT(*) FROM themes) t, (SELECT COUNT(*) FROM products) pr');
    }
}
