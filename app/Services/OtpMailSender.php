<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;
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

        if (! self::canSpawnBackgroundProcess()) {
            self::send($email, $name, $otp);

            return;
        }

        try {
            $process = new Process([
                PHP_BINARY,
                base_path('artisan'),
                'mail:send-otp',
                $email,
                $name,
                $otp,
            ], base_path());

            $process->setTimeout(null);
            $process->disableOutput();
            $process->start();

            Log::info('OTP mail background send started', [
                'email' => $email,
                'pid' => $process->getPid(),
            ]);
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

    protected static function canSpawnBackgroundProcess(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('proc_open', $disabled, true);
    }
}
