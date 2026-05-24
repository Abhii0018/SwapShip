<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpMailSender
{
    public static function send(string $email, string $name, string $otp): bool
    {
        $mailer = (string) config('mail.default');

        if ($mailer === 'log') {
            Log::warning('OTP mail skipped: MAIL_MAILER is log (emails are not sent)', ['email' => $email]);

            return false;
        }

        if ($mailer === 'smtp' && ! self::smtpIsConfigured()) {
            Log::error('OTP mail skipped: SMTP username/password missing on server', ['email' => $email]);

            return false;
        }

        try {
            $recipient = new User([
                'name' => $name,
                'email' => $email,
            ]);

            Mail::to($email)->send(new EmailVerificationOtpMail($recipient, $otp));

            Log::info('OTP verification email sent', ['email' => $email]);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            Log::error('OTP verification email failed', [
                'email' => $email,
                'mailer' => $mailer,
                'host' => config('mail.mailers.smtp.host'),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    protected static function smtpIsConfigured(): bool
    {
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        return $username !== '' && $password !== '';
    }
}
