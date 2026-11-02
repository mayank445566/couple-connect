<?php
declare(strict_types=1);

use App\Controllers\AuthController as A;
use App\Controllers\CoupleController as C;
use App\Controllers\MediaController as Md;
use App\Controllers\MessageController as M;
use App\Controllers\ProfileController as P;
use App\Controllers\ReportController as R;
use App\Controllers\SyncController as S;
use App\Core\Router;

$r = new Router();

$r->add('GET', '/health', [HealthController::class, 'show'], false);

// Auth
$r->add('POST', '/auth/register', [A::class, 'register'], false);
$r->add('POST', '/auth/login', [A::class, 'login'], false);
$r->add('POST', '/auth/forgot-password', [A::class, 'forgotPassword'], false);
$r->add('POST', '/auth/reset-password', [A::class, 'resetPassword'], false);
$r->add('POST', '/auth/verify-email', [A::class, 'verifyEmail']);
$r->add('POST', '/auth/resend-verification', [A::class, 'resendVerification']);
$r->add('POST', '/auth/logout', [A::class, 'logout']);
$r->add('POST', '/auth/logout-all', [A::class, 'logoutAll']);
$r->add('GET', '/auth/sessions', [A::class, 'sessions']);
$r->add('DELETE', '/auth/sessions/{id}', [A::class, 'revokeSession']);

// Profile
$r->add('GET', '/me', [P::class, 'me']);
$r->add('PATCH', '/me', [P::class, 'update']);
$r->add('POST', '/me/password', [P::class, 'changePassword']);
$r->add('POST', '/me/delete', [P::class, 'delete']);
$r->add('GET', '/me/export', [P::class, 'export']);

// Couple pairing
$r->add('POST', '/couple/invite', [C::class, 'createInvite']);
$r->add('POST', '/couple/join', [C::class, 'join']);
$r->add('GET', '/couple/requests', [C::class, 'requests']);
$r->add('POST', '/couple/requests/{id}/accept', [C::class, 'accept']);
$r->add('POST', '/couple/requests/{id}/reject', [C::class, 'reject']);
$r->add('POST', '/couple/requests/{id}/cancel', [C::class, 'cancel']);
$r->add('GET', '/couple', [C::class, 'show']);
$r->add('PATCH', '/couple', [C::class, 'update']);
$r->add('POST', '/couple/unpair', [C::class, 'unpair']);

// Messages
$r->add('GET', '/messages', [M::class, 'index']);
$r->add('POST', '/messages', [M::class, 'store']);
$r->add('GET', '/messages/starred', [M::class, 'starred']);
$r->add('GET', '/messages/pinned', [M::class, 'pinned']);
$r->add('GET', '/messages/search', [M::class, 'search']);
$r->add('POST', '/messages/delivered', [M::class, 'delivered']);
$r->add('POST', '/messages/read', [M::class, 'read']);
$r->add('PATCH', '/messages/{id}', [M::class, 'update']);
$r->add('DELETE', '/messages/{id}', [M::class, 'destroy']);
$r->add('POST', '/messages/{id}/reactions', [M::class, 'react']);
$r->add('DELETE', '/messages/{id}/reactions', [M::class, 'unreact']);
$r->add('POST', '/messages/{id}/pin', [M::class, 'pin']);
$r->add('DELETE', '/messages/{id}/pin', [M::class, 'unpin']);
$r->add('POST', '/messages/{id}/star', [M::class, 'star']);
$r->add('DELETE', '/messages/{id}/star', [M::class, 'unstar']);

// Sync and presence
$r->add('GET', '/sync', [S::class, 'sync']);
$r->add('POST', '/presence/typing', [S::class, 'typing']);

// Media
$r->add('POST', '/media', [Md::class, 'upload']);
$r->add('GET', '/media/{id}', [Md::class, 'download'], false);
$r->add('HEAD', '/media/{id}', [Md::class, 'download'], false);
$r->add('GET', '/media/{id}/link', [Md::class, 'link']);

// Safety
$r->add('POST', '/reports', [R::class, 'store']);

final class HealthController
{
    public function show(): array
    {
        \App\Core\Database::value('SELECT 1');
        return ['status' => 'ok', 'time' => gmdate('c')];
    }
}

return $r;
