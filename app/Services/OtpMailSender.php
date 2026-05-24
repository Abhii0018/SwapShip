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

        if (MailConfigurator::isRenderHost() && ! MailConfigurator::usesApiMailer()) {
            self::$lastError = 'Render blocks Gmail SMTP. Add SENDGRID_API_KEY on Render (free at sendgrid.com). Verify your Gmail as a Single Sender, then set MAIL_MAILER=sendgrid.';

            Log::error('OTP mail skipped on Render without API mailer', ['email' => $email]);

            return false;
        }

        $mailer = (string) config('mail.default');

        if ($mailer === 'log') {
            self::$lastError = 'MAIL_MAILER is "log" — no real emails are sent. On Render set MAIL_MAILER=sendgrid and SENDGRID_API_KEY.';

            Log::warning('OTP mail skipped', ['email' => $email, 'reason' => self::$lastError]);

            return false;
        }

        if ($mailer === 'smtp' && ! self::smtpIsConfigured()) {
            self::$lastError = 'MAIL_USERNAME or MAIL_PASSWORD is missing. On Render use SendGrid instead (SENDGRID_API_KEY).';

            Log::error('OTP mail skipped', ['email' => $email, 'reason' => self::$lastError]);

            return false;
        }

        try {
            $recipient = new User([
                'name' => $name,
                'email' => $email,
            ]);

            Mail::to($email)->send(new EmailVerificationOtpMail($recipient, $otp));

            Log::info('OTP verification email sent', ['email' => $email, 'mailer' => $mailer]);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            self::$lastError = self::humanizeError($exception->getMessage(), $mailer);

            Log::error('OTP verification email failed', [
                'email' => $email,
                'mailer' => $mailer,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public static function lastErrorMessage(): string
    {
        return self::$lastError ?? 'Email could not be sent.';
    }

    protected static function smtpIsConfigured(): bool
    {
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        return $username !== '' && $password !== '';
    }

    protected static function humanizeError(string $message, string $mailer): string
    {
        $lower = strtolower($message);

        if (MailConfigurator::isRenderHost() && ($mailer === 'smtp' || str_contains($lower, 'connection'))) {
            return 'Render blocks Gmail SMTP. Use SendGrid: add SENDGRID_API_KEY and MAIL_MAILER=sendgrid on Render (verify your Gmail as Single Sender at sendgrid.com).';
        }

        if (str_contains($lower, 'username and password not accepted') || str_contains($lower, 'authentication failed')) {
            return 'Email login rejected. On Render use SendGrid API key, not Gmail SMTP password.';
        }

        if (str_contains($lower, 'unauthorized') || str_contains($lower, '403') || str_contains($lower, '401')) {
            return 'SendGrid API key is invalid. Create a new key at sendgrid.com with Mail Send permission.';
        }

        if (strlen($message) > 200) {
            return substr($message, 0, 197).'...';
        }

        return $message;
    }
}
