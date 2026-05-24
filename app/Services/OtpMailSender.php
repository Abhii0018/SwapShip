<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use App\Support\MailConfigurator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpMailSender
{
    public static ?string $lastError = null;

    public static function send(string $email, string $name, string $otp): bool
    {
        self::$lastError = null;
        MailConfigurator::apply();

        $mailer = (string) config('mail.default');

        if ($mailer === 'log') {
            self::$lastError = 'MAIL_MAILER is "log" on the server — emails are not sent. On Render set MAIL_MAILER=smtp.';

            Log::warning('OTP mail skipped', ['email' => $email, 'reason' => self::$lastError]);

            return false;
        }

        if ($mailer === 'smtp' && ! self::smtpIsConfigured()) {
            self::$lastError = 'MAIL_USERNAME or MAIL_PASSWORD is missing on Render. Use your Gmail address and a 16-character App Password (no spaces).';

            Log::error('OTP mail skipped', ['email' => $email, 'reason' => self::$lastError]);

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
            self::$lastError = self::humanizeSmtpError($exception->getMessage());

            Log::error('OTP verification email failed', [
                'email' => $email,
                'mailer' => $mailer,
                'host' => config('mail.mailers.smtp.host'),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public static function lastErrorMessage(): string
    {
        return self::$lastError ?? 'Email could not be sent. Check MAIL_* variables on Render.';
    }

    protected static function smtpIsConfigured(): bool
    {
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        return $username !== '' && $password !== '';
    }

    protected static function humanizeSmtpError(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'username and password not accepted') || str_contains($lower, 'authentication failed')) {
            return 'Gmail rejected the login. Use a Gmail App Password (not your normal password) in MAIL_PASSWORD on Render.';
        }

        if (str_contains($lower, 'connection could not be established') || str_contains($lower, 'connection timed out')) {
            return 'Could not connect to Gmail SMTP. Set MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, MAIL_ENCRYPTION=tls on Render.';
        }

        if (strlen($message) > 180) {
            return substr($message, 0, 177).'...';
        }

        return $message;
    }
}
