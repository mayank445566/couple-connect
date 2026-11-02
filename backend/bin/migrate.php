<?php
declare(strict_types=1);
// Applies database/migrations/*.sql in order. Usage: php bin/migrate.php
// On cPanel without Terminal, the web installer (public/install.php) runs this for you.

require dirname(__DIR__) . '/src/bootstrap.php';

foreach (\App\Setup\Installer::migrate() as $v) {
    echo "Applied $v\n";
}
echo "Database is up to date.\n";
