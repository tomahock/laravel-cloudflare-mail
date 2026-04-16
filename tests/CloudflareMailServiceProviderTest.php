<?php

namespace Tomahock\CloudflareMail\Tests;

use Illuminate\Mail\MailManager;
use Orchestra\Testbench\TestCase;
use Tomahock\CloudflareMail\CloudflareMailServiceProvider;
use Tomahock\CloudflareMail\CloudflareTransport;

class CloudflareMailServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CloudflareMailServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cloudflare-mail.account_id', 'test-account');
        $app['config']->set('cloudflare-mail.api_token', 'test-token');
        $app['config']->set('cloudflare-mail.base_url', 'https://api.cloudflare.com/client/v4');
        $app['config']->set('cloudflare-mail.timeout', 30);

        $app['config']->set('mail.default', 'cloudflare');
        $app['config']->set('mail.mailers.cloudflare', ['transport' => 'cloudflare']);
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    public function test_config_is_accessible(): void
    {
        $this->assertNotNull(config('cloudflare-mail'));
    }

    public function test_config_has_account_id_key(): void
    {
        $this->assertArrayHasKey('account_id', config('cloudflare-mail'));
    }

    public function test_config_has_api_token_key(): void
    {
        $this->assertArrayHasKey('api_token', config('cloudflare-mail'));
    }

    public function test_config_has_base_url_key(): void
    {
        $this->assertArrayHasKey('base_url', config('cloudflare-mail'));
    }

    public function test_config_has_timeout_key(): void
    {
        $this->assertArrayHasKey('timeout', config('cloudflare-mail'));
    }

    public function test_config_base_url_defaults_to_cloudflare(): void
    {
        $this->assertSame('https://api.cloudflare.com/client/v4', config('cloudflare-mail.base_url'));
    }

    public function test_config_timeout_defaults_to_30(): void
    {
        $this->assertSame(30, config('cloudflare-mail.timeout'));
    }

    // -------------------------------------------------------------------------
    // Transport registration
    // -------------------------------------------------------------------------

    public function test_cloudflare_driver_is_registered_in_mail_manager(): void
    {
        $mailer = $this->app->make(MailManager::class)->mailer('cloudflare');

        $this->assertNotNull($mailer);
    }

    public function test_cloudflare_mailer_uses_cloudflare_transport(): void
    {
        $mailer = $this->app->make(MailManager::class)->mailer('cloudflare');
        $transport = $this->getTransport($mailer);

        $this->assertInstanceOf(CloudflareTransport::class, $transport);
    }

    public function test_transport_string_representation_is_cloudflare(): void
    {
        $mailer = $this->app->make(MailManager::class)->mailer('cloudflare');
        $transport = $this->getTransport($mailer);

        $this->assertSame('cloudflare', (string) $transport);
    }

    // -------------------------------------------------------------------------
    // Config publish tags
    // -------------------------------------------------------------------------

    public function test_config_publish_tag_is_registered(): void
    {
        $publishes = CloudflareMailServiceProvider::$publishes[CloudflareMailServiceProvider::class] ?? [];

        $this->assertNotEmpty($publishes);
    }

    public function test_config_is_published_under_correct_tag(): void
    {
        $groups = CloudflareMailServiceProvider::$publishGroups;

        $this->assertArrayHasKey('cloudflare-mail-config', $groups);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getTransport(mixed $mailer): mixed
    {
        // Laravel's Mailer exposes the underlying Symfony transport via getSymfonyTransport()
        if (method_exists($mailer, 'getSymfonyTransport')) {
            return $mailer->getSymfonyTransport();
        }

        // Fallback: read the protected property
        $reflection = new \ReflectionObject($mailer);
        $property = $reflection->getProperty('transport');
        $property->setAccessible(true);

        return $property->getValue($mailer);
    }
}
