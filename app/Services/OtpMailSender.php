<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpMailSender
{
    /**
     * Queue OTP email in a background process so the HTTP response returns immediately.
     */
    public static function dispatch(string $email, string $name, string $otp): void
    {
        if (config('mail.default') === 'log') {
            Log::warning('OTP mail skipped: MAIL_MAILER is set to log', ['email' => $email]);

            return;
        }

        if (app()->runningUnitTests()) {
            self::send($email, $name, $otp);

            return;
        }

        if (! self::canRunDetachedCommand()) {
            self::send($email, $name, $otp);

            return;
        }

        try {
            $command = sprintf(
                '%s %s mail:send-otp %s %s %s > /dev/null 2>&1 &',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(base_path('artisan')),
                escapeshellarg($email),
                escapeshellarg($name),
                escapeshellarg($otp)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \RuntimeException('Failed to start OTP mail background process.');
            }

            Log::info('OTP mail background send started', ['email' => $email]);
        } catch (Throwable $exception) {
            report($exception);
            self::send($email, $name, $otp);
        }
    }

    public static function send(string $email, string $name, string $otp): bool
    {
        if (config('mail.default') === 'log') {
            Log::warning('OTP mail skipped: MAIL_MAILER is set to log', ['email' => $email]);

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
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    protected static function canRunDetachedCommand(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('exec', $disabled, true);
    }
}
