<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Config;

/** RFC 6238 time-based one-time codes (works with Google Authenticator, Authy, 1Password, etc.). */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function uri(string $secret, string $email): string
    {
        $issuer = (string)Config::get('APP_NAME', 'Couple Connect') . ' Admin';
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }

    /** Returns the matched time step, or null. Accepts one step of clock drift either way. */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $step = intdiv($now ?? time(), 30);
        $key = self::unbase32($secret);
        foreach ([0, -1, 1] as $d) {
            $s = $step + $d;
            if ($lastStep !== null && $s <= $lastStep) {
                continue; // a code can be used only once
            }
            if (hash_equals(self::code($key, $s), $code)) {
                return $s;
            }
        }
        return null;
    }

    public static function code(string $key, int $step): string
    {
        $h = hash_hmac('sha1', pack('J', $step), $key, true);
        $o = ord($h[19]) & 0x0f;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Secrets are stored encrypted with a key derived from APP_KEY. */
    public static function seal(string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, self::boxKey()));
    }

    public static function open(string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        $plain = $raw === false ? false : sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::boxKey()
        );
        if ($plain === false) {
            throw new \RuntimeException('Cannot decrypt 2FA secret (APP_KEY changed?)');
        }
        return $plain;
    }

    private static function boxKey(): string
    {
        return sodium_crypto_generichash('admin-totp-v1', Config::key('APP_KEY'), SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function unbase32(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($s, '='))) as $c) {
            $i = strpos(self::ALPHABET, $c);
            if ($i === false) {
                throw new \InvalidArgumentException('Bad base32');
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
