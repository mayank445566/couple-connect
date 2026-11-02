<?php
declare(strict_types=1);
// Applies database/migrations/*.sql in order. Usage: php bin/migrate.php
// On cPanel: run once from Terminal, or let the installer (Phase 6) call it.

use App\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$dir = getenv('MIGRATIONS_PATH') ?: dirname(BASE_PATH) . '/database/migrations';
if (!is_dir($dir)) {
    $dir = BASE_PATH . '/database/migrations'; // deployment package layout
}
$pdo = Database::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$done = array_flip($pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob($dir . '/*.sql');
sort($files);
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (isset($done[$version])) {
        continue;
    }
    echo "Applying $version ... ";
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    echo "done\n";
}
echo "Database is up to date.\n";
