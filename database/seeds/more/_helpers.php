<?php
/**
 * Compact content format: one card per line, fields separated by " | ".
 *   English | Hindi                                   (plain question, truth, dare, challenge)
 *   English | Hindi | Opt A; Opt B | विकल्प A; विकल्प B   (choices)
 *   English | Hindi | A; B; C | A; B; C | Correct A        (quiz with a right answer)
 * Lines starting with # are comments.
 */

if (!function_exists('cc_cards')) {
    function cc_cards(string $kind, string $block, ?array $fixedOptions = null, ?array $fixedOptionsHi = null): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $block) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $f = array_map('trim', explode(' | ', $line));
            $row = [$kind, $f[0]];
            $opts = isset($f[2]) && $f[2] !== '' ? array_map('trim', explode(';', $f[2])) : $fixedOptions;
            $optsHi = isset($f[3]) && $f[3] !== '' ? array_map('trim', explode(';', $f[3])) : $fixedOptionsHi;
            if ($opts !== null) {
                $row[2] = $opts;
                if (isset($f[4]) && $f[4] !== '') {
                    $row[3] = $f[4];
                }
            }
            if (!empty($f[1])) {
                $row['hi'] = $f[1];
            }
            if ($optsHi !== null && $opts !== ['@couple'] && count($optsHi) === count($opts)) {
                $row['hio'] = $optsHi;
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Truth/dare block: lines start with "T:" or "D:". */
    function cc_tod(string $block): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $block) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $kind = str_starts_with($line, 'D:') ? 'dare' : 'truth';
            $out = array_merge($out, cc_cards($kind, trim(substr($line, 2))));
        }
        return $out;
    }
}
