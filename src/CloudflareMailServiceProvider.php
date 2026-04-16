<?php

namespace Tomahock\CloudflareMail;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class CloudflareMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cloudflare-mail.php', 'cloudflare-mail');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/cloudflare-mail.php' => config_path('cloudflare-mail.php'),
        ], 'cloudflare-mail-config');

        /** @var MailManager $manager */
        $manager = $this->app->make(MailManager::class);
        $manager->extend('cloudflare', function () {
            $config = $this->app['config']['cloudflare-mail'];

            return new CloudflareTransport(
                accountId: $config['account_id'],
                apiToken: $config['api_token'],
                baseUrl: $config['base_url'] ?? 'https://api.cloudflare.com/client/v4',
                timeout: $config['timeout'] ?? 30,
            );
        });
    }
}
