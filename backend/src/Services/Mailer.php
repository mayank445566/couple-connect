<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function send(string $to, string $subject, string $text): void
    {
        $driver = Config::get('MAIL_DRIVER', 'log');
        if ($driver === 'log') {
            $line = sprintf("[%s] To: %s | %s\n%s\n---\n", gmdate('c'), $to, $subject, $text);
            file_put_contents(Config::storage('logs') . '/mail.log', $line, FILE_APPEND | LOCK_EX);
            return;
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = Config::require('MAIL_HOST');
        $mail->Port = Config::int('MAIL_PORT', 465);
        $mail->SMTPAuth = true;
        $mail->Username = Config::require('MAIL_USER');
        $mail->Password = Config::require('MAIL_PASS');
        $mail->SMTPSecure = Config::get('MAIL_ENCRYPTION', 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(Config::require('MAIL_FROM'), Config::get('APP_NAME', 'Couple Connect'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $text;
        $mail->send();
    }
}
