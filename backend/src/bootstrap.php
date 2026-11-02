<?php
declare(strict_types=1);

use App\Core\Config;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Config::load(BASE_PATH . '/.env');

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

if (Config::get('APP_ENV') !== 'local') {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', Config::storage('logs') . '/php-error.log');
