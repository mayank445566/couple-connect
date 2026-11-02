<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Encrypts uploads at rest with XChaCha20-Poly1305 (libsodium), in independent
 * 64 KB chunks. Each chunk is authenticated with the file header and its index,
 * so chunks cannot be reordered, swapped between files, or truncated, and HTTP
 * Range requests (video seeking) can be served without decrypting the whole file.
 *
 * This protects files if the storage folder or a backup leaks. It is NOT
 * end-to-end encryption: the server holds MEDIA_KEY.
 *
 * File layout: "CCV1" | 16-byte nonce prefix | 8-byte plaintext size | chunks
 */
final class MediaVault
{
    public const CHUNK = 65536;
    private const MAGIC = 'CCV1';
    private const HEADER_LEN = 4 + 16 + 8;
    private const TAG = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

    private static function key(string $storageName): string
    {
        // Per-file subkey derived from the master key
        return sodium_crypto_generichash('media:' . $storageName, Config::key('MEDIA_KEY'), SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    public static function path(string $storageName): string
    {
        return Config::storage('media/' . substr($storageName, 0, 2)) . '/' . $storageName . '.bin';
    }

    public static function encryptFile(string $srcPath, string $storageName): void
    {
        $size = filesize($srcPath);
        $prefix = random_bytes(16);
        $header = self::MAGIC . $prefix . pack('J', $size);
        $key = self::key($storageName);
        $in = fopen($srcPath, 'rb');
        $tmp = self::path($storageName) . '.part';
        $out = fopen($tmp, 'wb');
        fwrite($out, $header);
        $i = 0;
        while (!feof($in)) {
            $plain = fread($in, self::CHUNK);
            if ($plain === '' || $plain === false) {
                break;
            }
            $idx = pack('J', $i);
            fwrite($out, sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $header . $idx, $prefix . $idx, $key));
            $i++;
        }
        fclose($in);
        fclose($out);
        rename($tmp, self::path($storageName));
        self::wipe($key);
    }

    /** Writes plaintext bytes [$start, $end] (inclusive) to output. */
    public static function stream(string $storageName, int $start, int $end): void
    {
        $fh = fopen(self::path($storageName), 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Media file missing');
        }
        $header = fread($fh, self::HEADER_LEN);
        if (strlen($header) !== self::HEADER_LEN || substr($header, 0, 4) !== self::MAGIC) {
            throw new \RuntimeException('Bad media header');
        }
        $prefix = substr($header, 4, 16);
        $key = self::key($storageName);
        $first = intdiv($start, self::CHUNK);
        $last = intdiv($end, self::CHUNK);
        fseek($fh, self::HEADER_LEN + $first * (self::CHUNK + self::TAG));
        for ($i = $first; $i <= $last; $i++) {
            $cipher = fread($fh, self::CHUNK + self::TAG);
            $idx = pack('J', $i);
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $header . $idx, $prefix . $idx, $key);
            if ($plain === false) {
                throw new \RuntimeException('Media integrity check failed');
            }
            $from = $i === $first ? $start - $i * self::CHUNK : 0;
            $to = $i === $last ? $end - $i * self::CHUNK : strlen($plain) - 1;
            echo substr($plain, $from, $to - $from + 1);
            flush();
        }
        fclose($fh);
        self::wipe($key);
    }

    /** Native sodium can wipe the key from memory; the pure-PHP fallback cannot, so just drop the reference. */
    private static function wipe(string &$key): void
    {
        if (extension_loaded('sodium')) {
            sodium_memzero($key);
        } else {
            $key = '';
        }
    }

    public static function delete(string $storageName): void
    {
        @unlink(self::path($storageName));
    }
}
