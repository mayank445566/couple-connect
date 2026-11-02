<?php
declare(strict_types=1);

namespace App\Core;

/** Small declarative validator. Rules: required, string, email, min:N, max:N, int, bool, date, in:a|b, uuid, adult */
final class Validator
{
    public static function check(array $data, array $rules): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleStr) {
            $rules = explode(',', $ruleStr);
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $present = $value !== null && $value !== '';
            if (!$present) {
                if (in_array('required', $rules, true)) {
                    $errors[$field] = 'This field is required.';
                }
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $err = match ($name) {
                    'required', '' => null,
                    'string' => is_string($value) ? null : 'Must be text.',
                    'email'  => (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 190) ? null : 'Enter a valid email address.',
                    'min'    => (is_string($value) && mb_strlen($value) >= (int)$arg) ? null : "Must be at least $arg characters.",
                    'max'    => (is_string($value) && mb_strlen($value) <= (int)$arg) ? null : "Must be at most $arg characters.",
                    'int'    => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'Must be a whole number.',
                    'bool'   => is_bool($value) || in_array($value, [0, 1, '0', '1'], true) ? null : 'Must be true or false.',
                    'date'   => self::isDate($value) ? null : 'Use the format YYYY-MM-DD.',
                    'in'     => in_array((string)$value, explode('|', (string)$arg), true) ? null : 'Invalid option.',
                    'uuid'   => (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) ? null : 'Must be a UUID.',
                    'adult'  => self::isDate($value) && self::age($value) >= Config::int('MIN_AGE', 18) ? null : 'You must be at least ' . Config::int('MIN_AGE', 18) . ' years old.',
                    'password' => self::passwordError($value),
                    default  => throw new \LogicException("Unknown rule $name"),
                };
                if ($err !== null) {
                    $errors[$field] = $err;
                    break;
                }
            }
            if (!isset($errors[$field])) {
                $clean[$field] = match (true) {
                    in_array('int', $rules, true)  => (int)$value,
                    in_array('bool', $rules, true) => (bool)$value,
                    in_array('email', $rules, true) => strtolower($value),
                    default => $value,
                };
            }
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }
        return $clean;
    }

    public static function isDate(mixed $v): bool
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y) && $y >= 1900;
    }

    public static function age(string $dob): int
    {
        return (new \DateTimeImmutable($dob))->diff(new \DateTimeImmutable('today'))->y;
    }

    private static function passwordError(mixed $v): ?string
    {
        if (!is_string($v) || mb_strlen($v) < 8) {
            return 'Use at least 8 characters.';
        }
        if (mb_strlen($v) > 128) {
            return 'Use at most 128 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) {
            return 'Use at least one letter and one number.';
        }
        return null;
    }
}
