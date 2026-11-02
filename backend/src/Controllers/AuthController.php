<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Services\Codes;
use App\Services\Mailer;
use App\Services\Present;

final class AuthController
{
    public function register(Request $req): array
    {
        RateLimiter::hit('register:ip:' . $req->ip(), 10, 3600);
        $d = Validator::check($req->json(), [
            'email' => 'required,email',
            'password' => 'required,password',
            'display_name' => 'required,string,min:1,max:60',
            'dob' => 'required,date,adult',
            'relationship_status' => 'in:dating|engaged|married|long_distance|other',
            'accept_terms' => 'required,bool',
            'device_name' => 'string,max:100',
            'platform' => 'in:android|ios|web',
        ]);
        if (!$d['accept_terms']) {
            throw ApiException::validation(['accept_terms' => 'You must accept the terms to continue.']);
        }
        if (Database::value('SELECT 1 FROM users WHERE email = ?', [$d['email']])) {
            throw ApiException::validation(['email' => 'An account with this email already exists.']);
        }
        $userId = Database::insert(
            'INSERT INTO users (email, password_hash, display_name, dob, relationship_status) VALUES (?, ?, ?, ?, ?)',
            [$d['email'], Auth::hashPassword($d['password']), $d['display_name'], $d['dob'], $d['relationship_status'] ?? 'dating']
        );
        $this->sendVerification($userId, $d['email'], $d['display_name']);
        [$token] = Auth::issue($userId, $req, $d['device_name'] ?? null, $d['platform'] ?? null);
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$userId]);
        return Router::status(201, ['token' => $token, 'user' => Present::me($user)]);
    }

    public function login(Request $req): array
    {
        $d = Validator::check($req->json(), [
            'email' => 'required,email',
            'password' => 'required,string,max:128',
            'device_name' => 'string,max:100',
            'platform' => 'in:android|ios|web',
        ]);
        $emailKey = 'login:email:' . $d['email'];
        $ipKey = 'login:ip:' . $req->ip();
        if (RateLimiter::blocked($emailKey, 5) || RateLimiter::blocked($ipKey, 30)) {
            throw new ApiException(429, 'rate_limited', 'Too many failed attempts. Try again in 15 minutes or reset your password.');
        }
        $user = Database::one('SELECT * FROM users WHERE email = ?', [$d['email']]);
        // Verify against a dummy hash when the user does not exist, so timing does not reveal accounts
        $hash = $user['password_hash'] ?? Auth::DUMMY_HASH;
        if (!password_verify($d['password'], $hash) || $user === null || $user['status'] === 'deleted') {
            RateLimiter::hit($emailKey, PHP_INT_MAX, 900);
            RateLimiter::hit($ipKey, PHP_INT_MAX, 900);
            throw new ApiException(401, 'invalid_credentials', 'Email or password is incorrect.');
        }
        if (in_array($user['status'], ['suspended', 'banned'], true)) {
            throw new ApiException(403, 'account_' . $user['status'], 'This account is ' . $user['status'] . '. Contact support.');
        }
        RateLimiter::clear($emailKey);
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($d['password']), $user['id']]);
        }
        // Logging in cancels a pending account deletion
        if ($user['deletion_requested_at'] !== null) {
            Database::run('UPDATE users SET deletion_requested_at = NULL WHERE id = ?', [$user['id']]);
            $user['deletion_requested_at'] = null;
        }
        [$token] = Auth::issue((int)$user['id'], $req, $d['device_name'] ?? null, $d['platform'] ?? null);
        return ['token' => $token, 'user' => Present::me($user)];
    }

    public function logout(Request $req, array $user): array
    {
        Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$user['token_id']]);
        return ['logged_out' => true];
    }

    public function logoutAll(Request $req, array $user): array
    {
        $n = Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$user['id']])->rowCount();
        return ['revoked' => $n];
    }

    public function sessions(Request $req, array $user): array
    {
        $rows = Database::all(
            'SELECT id, device_name, platform, ip, created_at, last_used_at FROM auth_tokens
              WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY last_used_at DESC',
            [$user['id']]
        );
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'device_name' => $r['device_name'],
            'platform' => $r['platform'],
            'ip' => $r['ip'],
            'created_at' => Present::iso($r['created_at']),
            'last_used_at' => Present::iso($r['last_used_at']),
            'current' => (int)$r['id'] === (int)$user['token_id'],
        ], $rows);
    }

    public function revokeSession(Request $req, array $user): array
    {
        $n = Database::run(
            'UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND revoked_at IS NULL',
            [(int)$req->params['id'], $user['id']]
        )->rowCount();
        if ($n === 0) {
            throw new ApiException(404, 'not_found', 'Session not found.');
        }
        return ['revoked' => true];
    }

    public function verifyEmail(Request $req, array $user): array
    {
        $d = Validator::check($req->json(), ['code' => 'required,string,min:6,max:6']);
        RateLimiter::hit('verify:user:' . $user['id'], 10, 900);
        if ($user['email_verified_at'] !== null) {
            return ['email_verified' => true];
        }
        Codes::consume((int)$user['id'], 'verify_email', $d['code']);
        Database::run('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        return ['email_verified' => true];
    }

    public function resendVerification(Request $req, array $user): array
    {
        if ($user['email_verified_at'] !== null || $user['email'] === null) {
            return ['email_verified' => $user['email_verified_at'] !== null];
        }
        RateLimiter::hit('verify-resend:user:' . $user['id'], 3, 900);
        $this->sendVerification((int)$user['id'], $user['email'], $user['display_name']);
        return ['sent' => true];
    }

    /** Always answers the same way, so it cannot be used to discover registered emails. */
    public function forgotPassword(Request $req): array
    {
        $d = Validator::check($req->json(), ['email' => 'required,email']);
        RateLimiter::hit('forgot:ip:' . $req->ip(), 10, 3600);
        RateLimiter::hit('forgot:email:' . $d['email'], 3, 3600);
        $user = Database::one("SELECT id, email, display_name FROM users WHERE email = ? AND status IN ('active','suspended')", [$d['email']]);
        if ($user) {
            $code = Codes::create((int)$user['id'], 'reset_password');
            Mailer::send($user['email'], 'Your password reset code', "Hi {$user['display_name']},\n\nYour password reset code is: $code\n\nIt expires in 15 minutes. If you did not ask for this, you can ignore this email.");
        }
        return ['sent' => true];
    }

    public function resetPassword(Request $req): array
    {
        $d = Validator::check($req->json(), [
            'email' => 'required,email',
            'code' => 'required,string,min:6,max:6',
            'password' => 'required,password',
        ]);
        RateLimiter::hit('reset:email:' . $d['email'], 10, 3600);
        $user = Database::one('SELECT id FROM users WHERE email = ?', [$d['email']]);
        if (!$user) {
            throw new ApiException(400, 'invalid_code', 'That code is invalid or has expired.');
        }
        Codes::consume((int)$user['id'], 'reset_password', $d['code']);
        Database::transaction(function () use ($user, $d) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($d['password']), $user['id']]);
            // Sign out every device after a reset
            Database::run('UPDATE auth_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$user['id']]);
        });
        RateLimiter::clear('login:email:' . $d['email']);
        return ['reset' => true];
    }

    private function sendVerification(int $userId, string $email, string $name): void
    {
        $code = Codes::create($userId, 'verify_email');
        Mailer::send($email, 'Verify your email', "Hi $name,\n\nYour verification code is: $code\n\nIt expires in 15 minutes.");
    }
}
