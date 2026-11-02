<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Services\CoupleContext;

/** Relay tickets and push-notification device tokens. */
final class RealtimeController
{
    public const TICKET_TTL = 300;

    /**
     * Short-lived ticket for the WebSocket relay. The relay checks the HMAC with the shared
     * RELAY_SECRET and puts the socket in the couple's room. No database access on the relay.
     */
    public function ticket(Request $req, array $user): array
    {
        $ctx = CoupleContext::require($user);
        RateLimiter::hit('ticket:user:' . $ctx->userId, 120, 3600);
        $url = Config::get('RELAY_PUBLIC_URL');
        $secret = Config::get('RELAY_SECRET');
        if (!$url || !$secret) {
            return ['url' => null, 'ticket' => null, 'expires_at' => null];
        }
        $exp = time() + self::TICKET_TTL;
        $payload = $ctx->userId . '.' . $ctx->id() . '.' . $exp . '.' . bin2hex(random_bytes(6));
        return [
            'url' => $url,
            'ticket' => $payload . '.' . hash_hmac('sha256', $payload, $secret),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $exp),
        ];
    }

    public function savePushToken(Request $req, array $user): array
    {
        RateLimiter::hit('push-token:user:' . $user['id'], 30, 3600);
        $token = trim((string)$req->input('token', ''));
        if ($token === '' || strlen($token) > 512 || !preg_match('/^[A-Za-z0-9_:\-\.]+$/', $token)) {
            throw ApiException::validation(['token' => 'Invalid push token.']);
        }
        $platform = in_array($req->input('platform'), ['android', 'ios', 'web'], true) ? $req->input('platform') : 'android';
        // A device token belongs to one signed-in session; re-registering moves it
        Database::run('DELETE FROM push_tokens WHERE token = ? OR auth_token_id = ?', [$token, $user['token_id']]);
        Database::insert('INSERT INTO push_tokens (user_id, auth_token_id, token, platform) VALUES (?, ?, ?, ?)', [$user['id'], $user['token_id'], $token, $platform]);
        return ['registered' => true];
    }

    public function deletePushToken(Request $req, array $user): array
    {
        $n = Database::run('DELETE FROM push_tokens WHERE auth_token_id = ?', [$user['token_id']])->rowCount();
        return ['removed' => $n];
    }
}
