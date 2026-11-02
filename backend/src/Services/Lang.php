<?php
declare(strict_types=1);

namespace App\Services;

/**
 * App language for content (games, packs, questions). The app sends Accept-Language;
 * anything that is not Hindi falls back to English. Untranslated rows fall back too.
 */
final class Lang
{
    public const SUPPORTED = ['en', 'hi'];

    public static function current(): string
    {
        $h = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        return str_starts_with(trim($h), 'hi') ? 'hi' : 'en';
    }

    /** $row[$field . '_hi'] when the app is in Hindi and a translation exists, else $row[$field]. */
    public static function pick(array $row, string $field): ?string
    {
        if (self::current() === 'hi') {
            $v = $row[$field . '_hi'] ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        return $row[$field] ?? null;
    }

    /** Translated option labels, in the same order as the canonical ones, or null. */
    public static function options(array $row): ?array
    {
        if (self::current() !== 'hi' || empty($row['options_hi'])) {
            return null;
        }
        $o = json_decode((string)$row['options_hi'], true);
        return is_array($o) ? $o : null;
    }
}
