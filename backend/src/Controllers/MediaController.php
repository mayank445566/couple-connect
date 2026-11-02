<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Services\Codes;
use App\Services\MediaVault;

final class MediaController
{
    private const ALLOWED = [
        'image'  => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'],
        'avatar' => ['image/jpeg', 'image/png', 'image/webp'],
        'video'  => ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'],
        'audio'  => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/x-aac', 'audio/ogg', 'audio/opus', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/3gpp', 'video/mp4', 'video/3gpp', 'video/webm'],
        'file'   => ['application/pdf', 'text/plain', 'application/zip', 'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                     'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                     'image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'audio/mpeg'],
    ];
    private const MAX_MB = ['image' => 15, 'avatar' => 5, 'audio' => 20, 'voice' => 20];
    private const BLOCKED_EXT = ['php', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'exe', 'sh', 'bat', 'cmd', 'apk', 'jar', 'msi', 'dll', 'htaccess'];

    /** multipart/form-data: file=<binary>, kind=image|video|audio|voice|file|avatar, duration_ms? */
    public function upload(Request $req, array $user): array
    {
        RateLimiter::hit('upload:user:' . $user['id'], 60, 3600);
        $kind = (string)($_POST['kind'] ?? '');
        if (!in_array($kind, ['image', 'video', 'audio', 'voice', 'file', 'avatar'], true)) {
            throw ApiException::validation(['kind' => 'Choose image, video, audio, voice, file or avatar.']);
        }
        if ($kind !== 'avatar' && $user['active_couple_id'] === null) {
            throw new ApiException(409, 'not_paired', 'Connect with your partner first.');
        }
        $f = $_FILES['file'] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
            throw ApiException::validation(['file' => in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'File is too large.' : 'Upload failed. Try again.']);
        }
        $maxMb = self::MAX_MB[$kind] ?? Config::int('MAX_UPLOAD_MB', 50);
        if ($f['size'] > $maxMb * 1024 * 1024 || $f['size'] === 0) {
            throw ApiException::validation(['file' => "File must be smaller than {$maxMb} MB."]);
        }
        $name = $this->safeName((string)$f['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, self::BLOCKED_EXT, true)) {
            throw ApiException::validation(['file' => 'This file type is not allowed.']);
        }
        // Trust the bytes, never the client-provided type
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
        $allowed = self::ALLOWED[$kind === 'voice' ? 'audio' : $kind];
        if (!in_array($mime, $allowed, true)) {
            throw ApiException::validation(['file' => "This file type ($mime) is not allowed here."]);
        }

        $path = $f['tmp_name'];
        $width = $height = null;
        if (in_array($kind, ['image', 'avatar'], true) && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            [$path, $mime, $width, $height] = $this->sanitizeImage($path, $mime, $kind === 'avatar' ? 640 : 2560);
        }
        $duration = isset($_POST['duration_ms']) ? max(0, min(86400000, (int)$_POST['duration_ms'])) : null;
        $storage = bin2hex(random_bytes(24));
        MediaVault::encryptFile($path, $storage);
        $size = filesize($path);
        if ($path !== $f['tmp_name']) {
            @unlink($path);
        }
        $id = Database::insert(
            'INSERT INTO media (owner_id, couple_id, kind, mime, original_name, size_bytes, width, height, duration_ms, storage_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$user['id'], $kind === 'avatar' ? null : $user['active_couple_id'], $kind, $mime, $name, $size, $width, $height, $duration, $storage]
        );
        return Router::status(201, [
            'id' => $id, 'kind' => $kind, 'mime' => $mime, 'size' => $size,
            'width' => $width, 'height' => $height, 'duration_ms' => $duration,
            'url' => rtrim((string)Config::get('APP_URL'), '/') . '/media/' . $id,
        ]);
    }

    /**
     * GET /media/{id}. Auth by bearer token, or by a short-lived signed link from
     * GET /media/{id}/link (for players that cannot send headers). Supports Range.
     */
    public function download(Request $req): never
    {
        $id = (int)$req->params['id'];
        $viewerId = null;
        if ($req->bearerToken() !== null) {
            $viewerId = (int)Auth::require($req)['id'];
        } elseif (isset($req->query['uid'], $req->query['exp'], $req->query['sig'])) {
            $uid = (int)$req->query['uid'];
            $exp = (int)$req->query['exp'];
            $expected = Codes::hmac("media:$id:$uid:$exp");
            if ($exp >= time() && hash_equals($expected, (string)$req->query['sig'])) {
                $viewerId = $uid;
            }
        }
        if ($viewerId === null) {
            throw new ApiException(401, 'unauthenticated', 'Sign in required.');
        }
        $media = $this->accessible($id, $viewerId);

        $size = (int)$media['size_bytes'];
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $req->header('range');
        if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int)$m[2]);
            } else {
                $start = (int)$m[1];
                $end = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            $status = 206;
        }
        http_response_code($status);
        header('Content-Type: ' . $media['mime']);
        header('Content-Length: ' . ($end - $start + 1));
        header('Accept-Ranges: bytes');
        if ($status === 206) {
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        $disp = $media['kind'] === 'file' ? 'attachment' : 'inline';
        header("Content-Disposition: $disp; filename=\"" . addcslashes((string)($media['original_name'] ?: 'file'), '"\\') . '"');
        if ($req->method !== 'HEAD') {
            MediaVault::stream($media['storage_name'], $start, $end);
        }
        exit;
    }

    public function link(Request $req, array $user): array
    {
        $id = (int)$req->params['id'];
        $this->accessible($id, (int)$user['id']);
        $exp = time() + 900;
        $uid = (int)$user['id'];
        $sig = Codes::hmac("media:$id:$uid:$exp");
        return [
            'url' => rtrim((string)Config::get('APP_URL'), '/') . "/media/$id?uid=$uid&exp=$exp&sig=$sig",
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $exp),
        ];
    }

    /** Owner, the active partner in the same couple, or (avatars only) a pairing counterpart. */
    private function accessible(int $id, int $viewerId): array
    {
        $media = Database::one('SELECT * FROM media WHERE id = ? AND deleted_at IS NULL', [$id]);
        $viewer = Database::one("SELECT id, active_couple_id FROM users WHERE id = ? AND status = 'active'", [$viewerId]);
        $ok = false;
        if ($media && $viewer) {
            if ((int)$media['owner_id'] === $viewerId) {
                $ok = true;
            } elseif ($media['couple_id'] !== null) {
                $ok = $viewer['active_couple_id'] !== null && (int)$viewer['active_couple_id'] === (int)$media['couple_id'];
            } elseif ($media['kind'] === 'avatar') {
                $ok = (bool)Database::value(
                    "SELECT 1 FROM users o WHERE o.id = ? AND o.avatar_media_id = ? AND (
                        (o.active_couple_id IS NOT NULL AND o.active_couple_id = ?)
                        OR EXISTS (SELECT 1 FROM pair_requests pr WHERE pr.status = 'pending_approval'
                                     AND ((pr.inviter_id = o.id AND pr.invitee_id = ?) OR (pr.invitee_id = o.id AND pr.inviter_id = ?))))",
                    [$media['owner_id'], $id, $viewer['active_couple_id'] ?? 0, $viewerId, $viewerId]
                );
            }
        }
        if (!$ok) {
            // Same answer for "missing" and "not yours", so ids cannot be probed
            throw new ApiException(404, 'not_found', 'File not found.');
        }
        return $media;
    }

    /** Re-encodes images: strips EXIF/GPS metadata, fixes rotation, caps dimensions. */
    private function sanitizeImage(string $path, string $mime, int $maxDim): array
    {
        if (!function_exists('imagecreatefromstring')) {
            $info = @getimagesize($path);
            return [$path, $mime, $info[0] ?? null, $info[1] ?? null];
        }
        $img = @imagecreatefromstring((string)file_get_contents($path));
        if ($img === false) {
            throw ApiException::validation(['file' => 'This image could not be read.']);
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($angle) {
                $img = imagerotate($img, $angle, 0);
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxDim / max($w, $h));
        if ($scale < 1) {
            $nw = (int)round($w * $scale);
            $nh = (int)round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
            [$w, $h] = [$nw, $nh];
        }
        $out = tempnam(sys_get_temp_dir(), 'cci');
        if ($mime === 'image/png') {
            imagesavealpha($img, true);
            imagepng($img, $out, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagewebp($img, $out, 82);
        } else {
            imagejpeg($img, $out, 82);
            $mime = 'image/jpeg';
        }
        imagedestroy($img);
        return [$out, $mime, $w, $h];
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}\.\-_ ]/u', '_', $name) ?? 'file';
        return mb_substr($name, -120) ?: 'file';
    }
}
