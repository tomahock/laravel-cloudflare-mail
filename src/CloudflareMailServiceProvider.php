<?php

namespace Tomahock\CloudflareMail;

use Illuminate\Contracts\Foundation\Application;
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

        $this->app->resolving(MailManager::class, function (MailManager $manager, Application $app) {
            $manager->extend('cloudflare', function () use ($app) {
                $config = $app['config']['cloudflare-mail'];

                return new CloudflareTransport(
                    accountId: $config['account_id'],
                    apiToken: $config['api_token'],
                    baseUrl: $config['base_url'] ?? 'https://api.cloudflare.com/client/v4',
                    timeout: $config['timeout'] ?? 30,
                );
            });
        });
    }
}
