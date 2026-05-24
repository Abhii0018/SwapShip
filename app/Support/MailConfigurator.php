<?php

namespace App\Support;

class MailConfigurator
{
    public static function apply(): void
    {
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
}
