<?php

namespace App\Services;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use App\Support\MailConfigurator;
use Illuminate\Support\Facades\Http;
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
            self::$lastError = 'Render blocks Gmail SMTP. Add SENDGRID_API_KEY on Render and set MAIL_MAILER=sendgrid.';

            return false;
        }

        $mailer = (string) config('mail.default');

        if ($mailer === 'sendgrid') {
            return self::sendViaSendGridApi($email, $name, $otp);
        }

        if ($mailer === 'log') {
            self::$lastError = 'MAIL_MAILER is "log" — set MAIL_MAILER=sendgrid and SENDGRID_API_KEY on Render.';

            return false;
        }

        if ($mailer === 'smtp' && ! self::smtpIsConfigured()) {
            self::$lastError = 'SMTP credentials missing. On Render use SENDGRID_API_KEY instead.';

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

    protected static function sendViaSendGridApi(string $email, string $name, string $otp): bool
    {
        $apiKey = MailConfigurator::normalizedSendgridKey();

        if ($apiKey === '') {
            self::$lastError = 'SENDGRID_API_KEY is empty on Render. Paste your SG.xxx key (no quotes).';

            return false;
        }

        if (! str_starts_with($apiKey, 'SG.')) {
            self::$lastError = 'SENDGRID_API_KEY must start with SG. Remove quotes/spaces in Render env.';

            return false;
        }

        $fromEmail = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name', 'SwapShip'));

        if ($fromEmail === '' || $fromEmail === 'hello@example.com') {
            self::$lastError = 'MAIL_FROM_ADDRESS is not set. Please set it in your .env file to a verified SendGrid sender email.';

            return false;
        }

        try {
            $html = view('emails.auth.verify-otp', [
                'name' => $name,
                'otp' => $otp,
            ])->render();
        } catch (Throwable $exception) {
            report($exception);
            self::$lastError = 'Could not build OTP email template.';

            return false;
        }

        $plainText = "Hi {$name},\n\nYour SwapShip verification code is: {$otp}\n\nThis code expires in 10 minutes.\n\nIf you did not request this, ignore this email.";

        try {
            $response = Http::timeout(20)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.sendgrid.com/v3/mail/send', [
                    'personalizations' => [
                        [
                            'to' => [
                                ['email' => $email],
                            ],
                        ],
                    ],
                    'from' => [
                        'email' => $fromEmail,
                        'name' => $fromName,
                    ],
                    'reply_to' => [
                        'email' => $fromEmail,
                        'name' => $fromName,
                    ],
                    'subject' => 'SwapShip Email Verification OTP',
                    'content' => [
                        [
                            'type' => 'text/plain',
                            'value' => $plainText,
                        ],
                        [
                            'type' => 'text/html',
                            'value' => $html,
                        ],
                    ],
                ]);

            if ($response->status() === 202) {
                Log::info('OTP sent via SendGrid API', ['email' => $email, 'from' => $fromEmail]);

                return true;
            }

            $errors = $response->json('errors');
            $detail = is_array($errors)
                ? collect($errors)->pluck('message')->filter()->implode(' ')
                : trim((string) $response->body());

            self::$lastError = self::humanizeSendGridResponse($detail, $response->status());

            Log::error('SendGrid API rejected OTP email', [
                'email' => $email,
                'from' => $fromEmail,
                'status' => $response->status(),
                'detail' => $detail,
            ]);

            return false;
        } catch (Throwable $exception) {
            report($exception);
            self::$lastError = 'SendGrid request failed: '.$exception->getMessage();

            return false;
        }
    }

    protected static function humanizeSendGridResponse(string $detail, int $status): string
    {
        $lower = strtolower($detail);

        if (str_contains($lower, 'sender') && (str_contains($lower, 'verified') || str_contains($lower, 'identity'))) {
            $from = trim((string) config('mail.from.address'));

            return 'SendGrid: sender not verified. In SendGrid → Settings → Sender Authentication, verify '
                .($from !== '' ? $from : 'your MAIL_FROM_ADDRESS')
                .' as a Single Sender.';
        }

        if ($status === 401 || str_contains($lower, 'authorization') || str_contains($lower, 'api key')) {
            return 'SendGrid API key rejected. Create a new key with Mail Send permission. On Render paste only SG.xxx with no quotes.';
        }

        if ($status === 403 && str_contains($lower, 'access forbidden')) {
            return 'SendGrid account not ready. Complete sender verification and trial setup in SendGrid dashboard.';
        }

        if ($detail !== '') {
            return 'SendGrid: '.$detail;
        }

        return 'SendGrid returned HTTP '.$status.'. Check sender verification and API key permissions.';
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
            return 'Render blocks Gmail SMTP. Use SENDGRID_API_KEY and MAIL_MAILER=sendgrid.';
        }

        if (strlen($message) > 220) {
            return substr($message, 0, 217).'...';
        }

        return $message;
    }
}
