<?php
declare(strict_types=1);

namespace App\Admin;

use App\Services\MediaVault;

/** Streams one decrypted media file to a signed-in admin. Never cached, never framed, never executed. */
final class MediaOut
{
    private const INLINE = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/webm', 'audio/aac', 'audio/x-m4a', 'audio/wav'];

    public static function send(array $media): never
    {
        $size = (int)$media['size_bytes'];
        $mime = (string)$media['mime'];
        $inline = in_array($mime, self::INLINE, true);
        header_remove('Content-Security-Policy');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . $size);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="evidence-' . (int)$media['id'] . '"');
        header('Cache-Control: no-store, private');
        if ($size > 0) {
            MediaVault::stream($media['storage_name'], 0, $size - 1);
        }
        exit;
    }
}
