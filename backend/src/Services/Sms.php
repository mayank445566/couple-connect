<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ApiException;
use App\Core\Config;

/**
 * Configurable SMS sender for OTPs: log (development), msg91 (India, DLT template),
 * twilio. Indian numbers need a DLT-registered sender and template for production.
 */
final class Sms
{
    public static function sendOtp(string $phone, string $code): void
    {
        $driver = Config::get('SMS_DRIVER', 'log');
        $text = "$code is your " . Config::get('APP_NAME', 'Couple Connect') . ' verification code. It expires in 10 minutes. Do not share it.';
        match ($driver) {
            'log' => file_put_contents(Config::storage('logs') . '/sms.log', sprintf("[%s] To: %s | %s\n", gmdate('c'), $phone, $text), FILE_APPEND | LOCK_EX),
            'msg91' => self::post(
                'https://control.msg91.com/api/v5/otp?' . http_build_query([
                    'template_id' => Config::require('MSG91_TEMPLATE_ID'),
                    'mobile' => ltrim($phone, '+'),
                    'otp' => $code,
                ]),
                '{}',
                ['Content-Type: application/json', 'authkey: ' . Config::require('MSG91_AUTH_KEY')]
            ),
            'twilio' => self::post(
                'https://api.twilio.com/2010-04-01/Accounts/' . Config::require('TWILIO_SID') . '/Messages.json',
                http_build_query(['To' => $phone, 'From' => Config::require('TWILIO_FROM'), 'Body' => $text]),
                ['Content-Type: application/x-www-form-urlencoded'],
                Config::require('TWILIO_SID') . ':' . Config::require('TWILIO_TOKEN')
            ),
            default => throw new \RuntimeException("Unknown SMS_DRIVER $driver"),
        };
    }

    private static function post(string $url, string $body, array $headers, ?string $basicAuth = null): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($basicAuth) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        }
        $res = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            error_log('SMS send failed: HTTP ' . $status . ' ' . substr((string)$res, 0, 300));
            throw new ApiException(502, 'sms_failed', 'Could not send the SMS. Please try again.');
        }
    }
}
