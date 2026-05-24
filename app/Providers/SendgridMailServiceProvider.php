<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class SendgridMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(SendgridTransportFactory::class)) {
            return;
        }

        Mail::extend('sendgrid', function (array $config) {
            $factory = new SendgridTransportFactory(null, HttpClient::create());

            $key = \App\Support\MailConfigurator::normalizedSendgridKey()
                ?: (string) ($config['key'] ?? '');

            return $factory->create(new Dsn(
                'sendgrid+api',
                'default',
                $key,
            ));
        });
    }
}
