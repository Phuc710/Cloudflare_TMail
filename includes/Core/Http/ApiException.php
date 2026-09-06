<?php
declare(strict_types=1);

namespace KaiMail\Core\Http;

use RuntimeException;
use Throwable;

/**
 * Standardized API Exception with HTTP status, error key, and extra payload.
 */
final class ApiException extends RuntimeException
{
    private int $statusCode;
    private string $errorType;
    private array $details;

    public function __construct(
        int $statusCode,
        string $message,
        string $errorType = 'ApiError',
        array $details = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public static function badRequest(string $message, array $details = []): self
    {
        return new self(400, $message, 'BadRequest', $details);
    }

    public static function unauthorized(string $message = 'Unauthorized', array $details = []): self
    {
        return new self(401, $message, 'Unauthorized', $details);
    }

    public static function forbidden(string $message = 'Forbidden', array $details = []): self
    {
        return new self(403, $message, 'Forbidden', $details);
    }

    public static function notFound(string $message = 'Not Found', array $details = []): self
    {
        return new self(404, $message, 'NotFound', $details);
    }

    public static function methodNotAllowed(string $message = 'Method Not Allowed'): self
    {
        return new self(405, $message, 'MethodNotAllowed');
    }

    public static function tooManyRequests(string $message = 'Too Many Requests', int $retryAfter = 60): self
    {
        return new self(429, $message, 'RateLimitExceeded', ['retry_after' => $retryAfter]);
    }

    public static function internal(string $message = 'Internal Server Error', array $details = []): self
    {
        return new self(500, $message, 'InternalError', $details);
    }
}
