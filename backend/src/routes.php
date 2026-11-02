<?php
declare(strict_types=1);

use App\Controllers\AuthController as A;
use App\Controllers\CallController as Cl;
use App\Controllers\RealtimeController as Rt;
use App\Controllers\CoupleController as C;
use App\Controllers\MediaController as Md;
use App\Controllers\MessageController as M;
use App\Controllers\ProfileController as P;
use App\Controllers\ReportController as R;
use App\Controllers\SyncController as S;
use App\Controllers\GameController as G;
use App\Controllers\HubController as H;
use App\Controllers\PhoneController as Ph;
use App\Controllers\StoreController as St;
use App\Controllers\ThemeController as T;
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

// Phone signup/login (SMS OTP)
$r->add('POST', '/auth/phone/request-otp', [Ph::class, 'requestOtp'], false);
$r->add('POST', '/auth/phone/register', [Ph::class, 'register'], false);
$r->add('POST', '/auth/phone/login', [Ph::class, 'login'], false);
$r->add('POST', '/me/phone', [Ph::class, 'attach']);

// Games (specific paths before /games/{slug})
$r->add('GET', '/games', [G::class, 'index']);
$r->add('POST', '/games/adult-consent', [G::class, 'adultConsent']);
$r->add('GET', '/games/sessions', [G::class, 'sessions']);
$r->add('POST', '/games/sessions', [G::class, 'start']);
$r->add('GET', '/games/sessions/{id}', [G::class, 'showSession']);
$r->add('POST', '/games/sessions/{id}/join', [G::class, 'join']);
$r->add('POST', '/games/sessions/{id}/decline', [G::class, 'decline']);
$r->add('POST', '/games/sessions/{id}/answer', [G::class, 'answer']);
$r->add('POST', '/games/sessions/{id}/skip', [G::class, 'skip']);
$r->add('POST', '/games/sessions/{id}/next', [G::class, 'next']);
$r->add('POST', '/games/sessions/{id}/judge', [G::class, 'judge']);
$r->add('POST', '/games/sessions/{id}/stop', [G::class, 'stop']);
$r->add('DELETE', '/games/custom-questions/{id}', [G::class, 'deleteCustom']);
$r->add('GET', '/games/{slug}', [G::class, 'show']);
$r->add('GET', '/games/{slug}/custom-questions', [G::class, 'listCustom']);
$r->add('POST', '/games/{slug}/custom-questions', [G::class, 'addCustom']);

// Daily question
$r->add('GET', '/daily', [G::class, 'daily']);
$r->add('POST', '/daily', [G::class, 'answerDaily']);
$r->add('GET', '/daily/history', [G::class, 'dailyHistory']);

// Store and payments
$r->add('GET', '/store/products', [St::class, 'products']);
$r->add('POST', '/store/orders', [St::class, 'createOrder']);
$r->add('POST', '/store/orders/{id}/verify', [St::class, 'verify']);
$r->add('GET', '/store/purchases', [St::class, 'purchases']);
$r->add('POST', '/store/restore', [St::class, 'restore']);
$r->add('POST', '/webhooks/razorpay', [St::class, 'webhook'], false);

// Themes and preferences
$r->add('GET', '/themes', [T::class, 'index']);
$r->add('GET', '/me/preferences', [T::class, 'preferences']);
$r->add('PATCH', '/me/preferences', [T::class, 'update']);

// Long-distance hub and shared space
$r->add('GET', '/home', [H::class, 'home']);
$r->add('POST', '/taps', [H::class, 'tap']);
$r->add('GET', '/taps', [H::class, 'taps']);
$r->add('GET', '/moods', [H::class, 'moods']);
$r->add('POST', '/moods', [H::class, 'setMood']);
$r->add('GET', '/notes', [H::class, 'notes']);
$r->add('POST', '/notes', [H::class, 'createNote']);
$r->add('GET', '/notes/{id}', [H::class, 'openNote']);
$r->add('PATCH', '/notes/{id}', [H::class, 'updateNote']);
$r->add('DELETE', '/notes/{id}', [H::class, 'deleteNote']);
$r->add('GET', '/hub/{slug}', [H::class, 'list']);
$r->add('POST', '/hub/{slug}', [H::class, 'create']);
$r->add('PATCH', '/hub/{slug}/{id}', [H::class, 'update']);
$r->add('DELETE', '/hub/{slug}/{id}', [H::class, 'delete']);

// Real-time relay and push notifications
$r->add('POST', '/realtime/ticket', [Rt::class, 'ticket']);
$r->add('POST', '/me/push-token', [Rt::class, 'savePushToken']);
$r->add('DELETE', '/me/push-token', [Rt::class, 'deletePushToken']);

// Voice and video calls
$r->add('GET', '/calls', [Cl::class, 'index']);
$r->add('POST', '/calls', [Cl::class, 'start']);
$r->add('GET', '/calls/turn', [Cl::class, 'turn']);
$r->add('GET', '/calls/{id}', [Cl::class, 'show']);
$r->add('POST', '/calls/{id}/accept', [Cl::class, 'accept']);
$r->add('POST', '/calls/{id}/decline', [Cl::class, 'decline']);
$r->add('POST', '/calls/{id}/end', [Cl::class, 'end']);
$r->add('POST', '/calls/{id}/signal', [Cl::class, 'signal']);
$r->add('GET', '/calls/{id}/signals', [Cl::class, 'signals']);

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
