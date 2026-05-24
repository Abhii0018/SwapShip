<?php

namespace App\Support;

class MailConfigurator
{
    public static function apply(): void
    {
        $sendgridKey = self::normalizedSendgridKey();

        if ($sendgridKey !== '') {
            $from = trim((string) env('MAIL_FROM_ADDRESS', ''));
            $fromName = trim((string) env('MAIL_FROM_NAME', env('APP_NAME', 'SwapShip')));

            config([
                'mail.default' => 'sendgrid',
                'mail.mailers.sendgrid.key' => $sendgridKey,
            ]);

            if ($from !== '' && $from !== 'hello@example.com') {
                config(['mail.from.address' => $from]);
            }

            if ($fromName !== '') {
                config(['mail.from.name' => $fromName]);
            }

            return;
        }

        $mailer = strtolower(trim((string) env('MAIL_MAILER', '')));
        if ($mailer === 'sendgrid' && $sendgridKey === '') {
            config(['mail.default' => 'log']);
        }

        $host = trim((string) env('MAIL_HOST', ''));
        $username = trim((string) env('MAIL_USERNAME', ''));
        $password = trim((string) env('MAIL_PASSWORD', ''));
        $password = str_replace(' ', '', $password);

        if ($password !== '') {
            config(['mail.mailers.smtp.password' => $password]);
        }

        if ($username !== '') {
            config(['mail.mailers.smtp.username' => $username]);
        }

        if ($host !== '') {
            config(['mail.mailers.smtp.host' => $host]);
        }

        $scheme = env('MAIL_SCHEME');
        if ($scheme === null || $scheme === '' || $scheme === 'null') {
            config(['mail.mailers.smtp.scheme' => null]);
        }

        if (self::isRenderHost() && $host !== '') {
            config(['mail.mailers.smtp.timeout' => 3]);

            return;
        }

        $mailer = strtolower(trim((string) env('MAIL_MAILER', '')));
        if (($mailer === '' || $mailer === 'log') && $host !== '' && $username !== '' && $password !== '') {
            config(['mail.default' => 'smtp']);
        }

        $from = trim((string) env('MAIL_FROM_ADDRESS', ''));
        if ($from === '' || $from === 'hello@example.com') {
            if ($username !== '') {
                config(['mail.from.address' => $username]);
            }
        }
    }

    public static function normalizedSendgridKey(): string
    {
        $key = trim((string) env('SENDGRID_API_KEY', ''));
        $key = trim($key, " \t\n\r\0\x0B\"'");

        return str_replace(' ', '', $key);
    }

    public static function isRenderHost(): bool
    {
        if (filter_var(env('RENDER', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $url = (string) config('app.url', '');

        return str_contains($url, 'onrender.com');
    }

    public static function usesApiMailer(): bool
    {
        return self::normalizedSendgridKey() !== '';
    }
}
