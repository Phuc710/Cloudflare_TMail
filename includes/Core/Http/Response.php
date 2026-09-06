<?php
declare(strict_types=1);

namespace KaiMail\Core\Http;

/**
 * Standardized HTTP Response builder and emitter.
 */
final class Response
{
    private int $statusCode;
    private array $headers;
    private mixed $payload;

    public function __construct(int $statusCode = 200, mixed $payload = null, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->payload = $payload;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $res = new self($statusCode, $data, $headers);
        $res->header('Content-Type', 'application/json; charset=utf-8');
        return $res;
    }

    public static function success(array $data = [], int $statusCode = 200): self
    {
        return self::json(array_merge(['success' => true], $data), $statusCode);
    }

    public static function error(
        string $message,
        int $statusCode = 400,
        string $errorType = 'Error',
        array $extra = []
    ): self {
        return self::json(array_merge([
            'error' => $errorType,
            'message' => $message,
        ], $extra), $statusCode);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setNoCache(): self
    {
        $this->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->header('Pragma', 'no-cache');
        return $this;
    }

    public function setCors(Request $request, string $baseUrl): self
    {
        $origin = $request->getHeader('Origin');
        if ($origin !== '' && $request->isSameOrigin($baseUrl)) {
            $this->header('Access-Control-Allow-Origin', $origin);
            $this->header('Vary', 'Origin');
        }

        $this->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->header('Access-Control-Allow-Headers', 'Content-Type, X-API-KEY, X-API-TIMESTAMP, X-API-NONCE, X-API-SIGNATURE, X-WEB-UI-TOKEN, X-ADMIN-ACCESS-KEY');
        $this->header('Access-Control-Max-Age', '600');
        return $this;
    }

    public function setRateLimit(array $limit): self
    {
        if (isset($limit['limit'])) {
            $this->header('X-RateLimit-Limit', (string) $limit['limit']);
        }
        if (isset($limit['remaining'])) {
            $this->header('X-RateLimit-Remaining', (string) $limit['remaining']);
        }
        if (isset($limit['reset_at'])) {
            $this->header('X-RateLimit-Reset', (string) $limit['reset_at']);
        }
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        if ($this->payload !== null) {
            if (is_string($this->payload)) {
                echo $this->payload;
            } else {
                echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        exit;
    }
}
