<?php

namespace Tomahock\CloudflareMail\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomahock\CloudflareMail\Exceptions\CloudflareMailException;

class CloudflareMailExceptionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Inheritance
    // -------------------------------------------------------------------------

    public function test_is_runtime_exception(): void
    {
        $exception = new CloudflareMailException('message');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function test_message_is_set(): void
    {
        $exception = new CloudflareMailException('Something went wrong');

        $this->assertSame('Something went wrong', $exception->getMessage());
    }

    public function test_code_is_set(): void
    {
        $exception = new CloudflareMailException('msg', [], 422);

        $this->assertSame(422, $exception->getCode());
    }

    public function test_api_errors_default_to_empty_array(): void
    {
        $exception = new CloudflareMailException('msg');

        $this->assertSame([], $exception->getApiErrors());
    }

    public function test_api_errors_are_stored(): void
    {
        $errors = [
            ['code' => 1001, 'message' => 'Bad sender'],
            ['code' => 1002, 'message' => 'Bad recipient'],
        ];

        $exception = new CloudflareMailException('msg', $errors);

        $this->assertSame($errors, $exception->getApiErrors());
    }

    public function test_previous_exception_is_stored(): void
    {
        $previous = new \RuntimeException('original');
        $exception = new CloudflareMailException('wrapper', [], 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    // -------------------------------------------------------------------------
    // fromApiResponse — success path
    // -------------------------------------------------------------------------

    public function test_from_api_response_returns_instance(): void
    {
        $exception = CloudflareMailException::fromApiResponse(['errors' => []]);

        $this->assertInstanceOf(CloudflareMailException::class, $exception);
    }

    public function test_from_api_response_uses_first_error_message(): void
    {
        $response = [
            'errors' => [
                ['code' => 1001, 'message' => 'First error message'],
                ['code' => 1002, 'message' => 'Second error message'],
            ],
        ];

        $exception = CloudflareMailException::fromApiResponse($response);

        $this->assertStringContainsString('First error message', $exception->getMessage());
    }

    public function test_from_api_response_message_has_cloudflare_prefix(): void
    {
        $response = ['errors' => [['code' => 1001, 'message' => 'Bad token']]];

        $exception = CloudflareMailException::fromApiResponse($response);

        $this->assertStringStartsWith('Cloudflare Email Service error:', $exception->getMessage());
    }

    public function test_from_api_response_stores_all_errors(): void
    {
        $errors = [
            ['code' => 1001, 'message' => 'Error one'],
            ['code' => 1002, 'message' => 'Error two'],
        ];

        $exception = CloudflareMailException::fromApiResponse(['errors' => $errors]);

        $this->assertSame($errors, $exception->getApiErrors());
    }

    public function test_from_api_response_sets_http_status_as_code(): void
    {
        $exception = CloudflareMailException::fromApiResponse(['errors' => []], 400);

        $this->assertSame(400, $exception->getCode());
    }

    public function test_from_api_response_code_defaults_to_zero(): void
    {
        $exception = CloudflareMailException::fromApiResponse(['errors' => []]);

        $this->assertSame(0, $exception->getCode());
    }

    // -------------------------------------------------------------------------
    // fromApiResponse — fallback / edge cases
    // -------------------------------------------------------------------------

    public function test_from_api_response_uses_fallback_when_errors_is_empty(): void
    {
        $exception = CloudflareMailException::fromApiResponse(['errors' => []]);

        $this->assertStringContainsString('Unknown Cloudflare API error', $exception->getMessage());
    }

    public function test_from_api_response_uses_fallback_when_errors_key_missing(): void
    {
        $exception = CloudflareMailException::fromApiResponse([]);

        $this->assertStringContainsString('Unknown Cloudflare API error', $exception->getMessage());
    }

    public function test_from_api_response_api_errors_empty_when_no_errors_key(): void
    {
        $exception = CloudflareMailException::fromApiResponse([]);

        $this->assertSame([], $exception->getApiErrors());
    }

    public function test_from_api_response_handles_500_status(): void
    {
        $response = ['errors' => [['code' => 10000, 'message' => 'Internal server error']]];

        $exception = CloudflareMailException::fromApiResponse($response, 500);

        $this->assertSame(500, $exception->getCode());
        $this->assertStringContainsString('Internal server error', $exception->getMessage());
    }

    public function test_from_api_response_preserves_error_codes(): void
    {
        $errors = [['code' => 9999, 'message' => 'Custom error']];

        $exception = CloudflareMailException::fromApiResponse(['errors' => $errors]);

        $this->assertSame(9999, $exception->getApiErrors()[0]['code']);
    }
}
