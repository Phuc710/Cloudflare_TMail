<?php
declare(strict_types=1);

namespace KaiMail\Core\Http;

/**
 * Immutable, clean HTTP Request abstraction.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $body;
    private string $rawBody;
    private array $headers;
    private string $clientIp;
    private bool $isHttps;

    public function __construct(
        string $method,
        string $uri,
        string $path,
        array $query,
        array $body,
        string $rawBody,
        array $headers,
        string $clientIp,
        bool $isHttps
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->rawBody = $rawBody;
        $this->headers = $headers;
        $this->clientIp = $clientIp;
        $this->isHttps = $isHttps;
    }

    public static function capture(): self
    {
        $headers = self::extractHeaders();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $rawBody = (string) ($_SERVER['KAIMAIL_RAW_BODY'] ?? '');
        if ($rawBody === '') {
            $rawBody = (string) file_get_contents('php://input');
            $_SERVER['KAIMAIL_RAW_BODY'] = $rawBody;
        }

        $parsedJson = [];
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $parsedJson = $decoded;
            }
        }

        $body = !empty($_POST) ? array_merge($_POST, $parsedJson) : $parsedJson;

        // Handle method override for POST requests (Header, POST form, or JSON body)
        if ($method === 'POST') {
            $override = $headers['x-http-method-override'] ?? $body['_method'] ?? $_POST['_method'] ?? null;
            if (is_string($override) && $override !== '') {
                $method = strtoupper(trim($override));
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);

        $clientIp = self::resolveClientIp();
        $isHttps = self::resolveIsHttps();

        return new self(
            $method,
            $uri,
            $path,
            $_GET,
            $body,
            $rawBody,
            $headers,
            $clientIp,
            $isHttps
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function isHttps(): bool
    {
        return $this->isHttps;
    }

    public function isLocal(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $host = (string) preg_replace('/:\d+$/', '', $host);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return in_array($this->clientIp, ['127.0.0.1', '::1'], true);
    }

    public function getHeader(string $name, string $default = ''): string
    {
        $key = strtolower(str_replace('_', '-', trim($name)));
        return $this->headers[$key] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        $key = strtolower(str_replace('_', '-', trim($name)));
        return isset($this->headers[$key]) && $this->headers[$key] !== '';
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $val = $this->input($key, $default);
        return is_scalar($val) ? trim((string) $val) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $val = $this->input($key, $default);
        return is_numeric($val) ? (int) $val : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->input($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val === 1;
        }
        if (is_string($val)) {
            $lower = strtolower(trim($val));
            return in_array($lower, ['1', 'true', 'yes', 'on'], true);
        }
        return $default;
    }

    public function array(string $key, array $default = []): array
    {
        $val = $this->input($key, $default);
        return is_array($val) ? $val : $default;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getQuery(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function get(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function body(): array
    {
        return $this->body;
    }

    public function getJson(): array
    {
        return $this->body;
    }

    public function isSameOrigin(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $allowedOrigin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $allowedOrigin .= ':' . $parts['port'];
        }
        $allowedOrigin = rtrim(strtolower($allowedOrigin), '/');

        $origin = strtolower(trim($this->getHeader('Origin')));
        if ($origin !== '') {
            return rtrim($origin, '/') === $allowedOrigin;
        }

        $referer = trim($this->getHeader('Referer'));
        if ($referer === '') {
            return true;
        }

        $refParts = parse_url($referer);
        if (!is_array($refParts) || empty($refParts['scheme']) || empty($refParts['host'])) {
            return false;
        }

        $refOrigin = $refParts['scheme'] . '://' . $refParts['host'];
        if (!empty($refParts['port'])) {
            $refOrigin .= ':' . $refParts['port'];
        }

        return rtrim(strtolower($refOrigin), '/') === $allowedOrigin;
    }

    private static function extractHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $nativeHeaders = (array) getallheaders();
            foreach ($nativeHeaders as $name => $value) {
                $key = strtolower(str_replace('_', '-', (string) $name));
                $headers[$key] = trim((string) $value);
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                if (!isset($headers[$headerName])) {
                    $headers[$headerName] = trim((string) $value);
                }
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                if (!isset($headers[$headerName])) {
                    $headers[$headerName] = trim((string) $value);
                }
            }
        }

        return $headers;
    }

    private static function resolveClientIp(): string
    {
        $trustProxy = defined('API_TRUST_PROXY_HEADERS') && API_TRUST_PROXY_HEADERS;

        if ($trustProxy) {
            $cfIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
            if ($cfIp !== '') {
                return $cfIp;
            }

            $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($xff !== '') {
                $parts = array_map('trim', explode(',', $xff));
                if (isset($parts[0]) && $parts[0] !== '') {
                    return $parts[0];
                }
            }
        }

        return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    private static function resolveIsHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        if (defined('API_TRUST_PROXY_HEADERS') && API_TRUST_PROXY_HEADERS) {
            $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($proto === 'https') {
                return true;
            }
        }

        return ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443';
    }
}
