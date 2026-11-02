<?php
declare(strict_types=1);
// Loads starter games, packs, questions, themes and products. Safe to re-run:
// existing rows (matched by slug / text) are updated, never duplicated.
// Usage: php bin/seed.php

require dirname(__DIR__) . '/src/bootstrap.php';

$c = \App\Setup\Installer::seed();
echo "Seeded: {$c['g']} games, {$c['p']} packs, {$c['q']} questions, {$c['t']} themes, {$c['pr']} products\n";
