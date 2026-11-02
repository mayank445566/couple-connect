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
use App\Services\Present;
use App\Services\Sms;

/** Phone-number signup and login with SMS OTP. Numbers are stored in E.164 (+919876543210). */
final class PhoneController
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function requestOtp(Request $req): array
    {
        $phone = $this->phone($req->input('phone'));
        RateLimiter::hit('otp:phone:' . $phone, 3, 900);
        RateLimiter::hit('otp:ip:' . $req->ip(), 10, 3600);
        $code = Codes::sixDigits();
        Database::run('UPDATE phone_otps SET used_at = UTC_TIMESTAMP() WHERE phone = ? AND used_at IS NULL', [$phone]);
        Database::run(
            'INSERT INTO phone_otps (phone, code_hash, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)',
            [$phone, Codes::hmac('otp:' . $phone . ':' . $code), self::TTL_MINUTES]
        );
        Sms::sendOtp($phone, $code);
        // Same answer whether or not the number has an account
        return ['sent' => true, 'expires_in_seconds' => self::TTL_MINUTES * 60];
    }

    public function register(Request $req): array
    {
        RateLimiter::hit('register:ip:' . $req->ip(), 10, 3600);
        $phone = $this->phone($req->input('phone'));
        $d = Validator::check($req->json(), [
            'code' => 'required,string,min:6,max:6',
            'password' => 'required,password',
            'display_name' => 'required,string,min:1,max:60',
            'dob' => 'required,date,adult',
            'relationship_status' => 'in:dating|engaged|married|long_distance|other',
            'accept_terms' => 'required,bool',
            'email' => 'email',
            'device_name' => 'string,max:100',
            'platform' => 'in:android|ios|web',
        ]);
        if (!$d['accept_terms']) {
            throw ApiException::validation(['accept_terms' => 'You must accept the terms to continue.']);
        }
        if (Database::value('SELECT 1 FROM users WHERE phone = ?', [$phone])) {
            throw ApiException::validation(['phone' => 'This number already has an account. Sign in instead.']);
        }
        if (isset($d['email']) && Database::value('SELECT 1 FROM users WHERE email = ?', [$d['email']])) {
            throw ApiException::validation(['email' => 'An account with this email already exists.']);
        }
        $this->consume($phone, $d['code']);
        $id = Database::insert(
            'INSERT INTO users (phone, phone_verified_at, email, password_hash, display_name, dob, relationship_status) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?)',
            [$phone, $d['email'] ?? null, Auth::hashPassword($d['password']), $d['display_name'], $d['dob'], $d['relationship_status'] ?? 'dating']
        );
        [$token] = Auth::issue($id, $req, $d['device_name'] ?? null, $d['platform'] ?? null);
        return Router::status(201, ['token' => $token, 'user' => Present::me(Database::one('SELECT * FROM users WHERE id = ?', [$id]))]);
    }

    /** Login with phone + OTP code, or phone + password. */
    public function login(Request $req): array
    {
        $phone = $this->phone($req->input('phone'));
        $d = Validator::check($req->json(), ['code' => 'string,max:6', 'password' => 'string,max:128', 'device_name' => 'string,max:100', 'platform' => 'in:android|ios|web']);
        $key = 'login:phone:' . $phone;
        if (RateLimiter::blocked($key, 5)) {
            throw new ApiException(429, 'rate_limited', 'Too many failed attempts. Try again in 15 minutes.');
        }
        $user = Database::one("SELECT * FROM users WHERE phone = ? AND status <> 'deleted'", [$phone]);
        $ok = false;
        if (isset($d['code'])) {
            if ($user) {
                $this->consume($phone, $d['code']); // throws on a bad code
                $ok = true;
            }
        } elseif (isset($d['password'])) {
            $ok = password_verify($d['password'], $user['password_hash'] ?? Auth::DUMMY_HASH) && $user;
        } else {
            throw ApiException::validation(['code' => 'Enter the SMS code or your password.']);
        }
        if (!$ok) {
            RateLimiter::hit($key, PHP_INT_MAX, 900);
            throw new ApiException(401, 'invalid_credentials', 'Phone number or code is incorrect.');
        }
        if (in_array($user['status'], ['suspended', 'banned'], true)) {
            throw new ApiException(403, 'account_' . $user['status'], 'This account is ' . $user['status'] . '. Contact support.');
        }
        RateLimiter::clear($key);
        if ($user['deletion_requested_at'] !== null) {
            Database::run('UPDATE users SET deletion_requested_at = NULL WHERE id = ?', [$user['id']]);
        }
        [$token] = Auth::issue((int)$user['id'], $req, $d['device_name'] ?? null, $d['platform'] ?? null);
        return ['token' => $token, 'user' => Present::me($user)];
    }

    /** Add or change the phone number on a signed-in account (OTP sent with request-otp first). */
    public function attach(Request $req, array $user): array
    {
        $phone = $this->phone($req->input('phone'));
        $code = (string)$req->input('code', '');
        if (Database::value('SELECT 1 FROM users WHERE phone = ? AND id <> ?', [$phone, $user['id']])) {
            throw ApiException::validation(['phone' => 'This number is linked to another account.']);
        }
        $this->consume($phone, $code);
        Database::run('UPDATE users SET phone = ?, phone_verified_at = UTC_TIMESTAMP() WHERE id = ?', [$phone, $user['id']]);
        return Present::me(Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]));
    }

    private function consume(string $phone, string $code): void
    {
        $row = Database::one('SELECT * FROM phone_otps WHERE phone = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1', [$phone]);
        $invalid = new ApiException(400, 'invalid_code', 'That code is invalid or has expired.');
        if (!$row || (int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            throw $invalid;
        }
        if (!hash_equals($row['code_hash'], Codes::hmac('otp:' . $phone . ':' . $code))) {
            Database::run('UPDATE phone_otps SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            throw $invalid;
        }
        Database::run('UPDATE phone_otps SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$row['id']]);
    }

    /** Accepts +<country><number>, or a 10-digit Indian number which becomes +91. */
    private function phone(mixed $raw): string
    {
        $p = preg_replace('/[\s\-()]/', '', (string)$raw);
        if (preg_match('/^[6-9]\d{9}$/', $p)) {
            $p = '+91' . $p;
        }
        if (!preg_match('/^\+[1-9]\d{7,14}$/', $p)) {
            throw ApiException::validation(['phone' => 'Enter a valid mobile number with country code, e.g. +919876543210.']);
        }
        return $p;
    }
}
