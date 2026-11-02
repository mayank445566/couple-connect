<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Business settings editable from the admin panel (stored in the settings table). */
final class Settings
{
    public const DEFAULTS = [
        // One purchase unlocks premium for the buyer's current partner as well
        'premium_shared_with_partner' => '1',
        // Premium themes are part of the ₹199 unlock (product grants decide; this flag is shown to clients)
        'themes_in_premium' => '1',
        'adult_section_enabled' => '1',
        'daily_question_pack' => 'daily-questions',
        'payment_gateway' => 'razorpay',
    ];

    private static ?array $cache = null;

    public static function get(string $key): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT k, v FROM settings') as $r) {
                self::$cache[$r['k']] = (string)$r['v'];
            }
        }
        return self::$cache[$key] ?? self::DEFAULTS[$key] ?? '';
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(string $key, string $value): void
    {
        Database::run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
        self::$cache = null;
    }
}
