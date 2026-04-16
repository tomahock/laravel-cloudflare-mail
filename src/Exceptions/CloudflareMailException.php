<?php

namespace Tomahock\CloudflareMail\Exceptions;

use RuntimeException;

class CloudflareMailException extends RuntimeException
{
    /** @var array<int, array{code: int, message: string}> */
    private array $apiErrors;

    /**
     * @param array<int, array{code: int, message: string}> $apiErrors
     */
    public function __construct(string $message, array $apiErrors = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->apiErrors = $apiErrors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<int, array{code: int, message: string}>
     */
    public function getApiErrors(): array
    {
        return $this->apiErrors;
    }

    public static function fromApiResponse(array $response, int $httpStatus = 0): self
    {
        $errors = $response['errors'] ?? [];
        $firstMessage = $errors[0]['message'] ?? 'Unknown Cloudflare API error';

        return new self(
            message: "Cloudflare Email Service error: {$firstMessage}",
            apiErrors: $errors,
            code: $httpStatus,
        );
    }
}
