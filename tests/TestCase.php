<?php

namespace Tomahock\CloudflareMail\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tomahock\CloudflareMail\CloudflareTransport;

abstract class TestCase extends BaseTestCase
{
    protected array $requestHistory = [];

    protected function makeTransport(
        array $responses,
        string $accountId = 'test-account-id',
        string $apiToken = 'test-api-token',
        string $baseUrl = 'https://api.cloudflare.com/client/v4',
        int $timeout = 30,
    ): CloudflareTransport {
        $this->requestHistory = [];

        // Use a bare HandlerStack (no http_errors middleware) so 4xx/5xx responses
        // are returned to application code rather than thrown by Guzzle.
        $mock = new MockHandler($responses);
        $stack = new HandlerStack($mock);
        $stack->push(Middleware::history($this->requestHistory));

        $client = new Client(['handler' => $stack]);

        return new CloudflareTransport(
            accountId: $accountId,
            apiToken: $apiToken,
            baseUrl: $baseUrl,
            timeout: $timeout,
            client: $client,
        );
    }

    protected function successResponse(array $merge = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(array_merge([
            'success' => true,
            'result' => [
                'delivered' => ['recipient@example.com'],
                'queued' => [],
                'permanent_bounces' => [],
            ],
            'errors' => [],
            'messages' => [],
        ], $merge)));
    }

    protected function errorResponse(array $errors = [], int $status = 400): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'success' => false,
            'result' => null,
            'errors' => $errors,
            'messages' => [],
        ]));
    }

    protected function lastRequestPayload(): array
    {
        return json_decode((string) $this->requestHistory[0]['request']->getBody(), true);
    }

    protected function lastRequestHeader(string $name): string
    {
        return $this->requestHistory[0]['request']->getHeaderLine($name);
    }

    protected function lastRequestUri(): string
    {
        return (string) $this->requestHistory[0]['request']->getUri();
    }
}
