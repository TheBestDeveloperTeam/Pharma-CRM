<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    private array  $body;
    private array  $headers;
    private string $rawBody;
    private string $clientIp;

    public readonly string $method;
    public readonly string $path;
    public readonly array  $query;

    /** @var array<string,string> URL route parameters set by Router */
    public array $params = [];

    private function __construct(
        string $method,
        string $path,
        array  $query,
        string $rawBody,
        array  $headers,
        string $clientIp,
    ) {
        $this->method   = $method;
        $this->path     = $path;
        $this->query    = $query;
        $this->rawBody  = $rawBody;
        $this->headers  = $headers;
        $this->clientIp = $clientIp;
        $this->body     = [];

        // Parse JSON body (only for applicable methods)
        if ($rawBody !== '' && in_array($method, ['POST','PUT','PATCH'], true)) {
            if (strlen($rawBody) > 1_048_576) { // 1 MB limit
                throw new \App\Core\Exceptions\ValidationException('REQUEST_BODY_TOO_LARGE');
            }
            $decoded = json_decode($rawBody, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \App\Core\Exceptions\ValidationException('REQUEST_BODY_INVALID_JSON');
            }
            $this->body = is_array($decoded) ? $decoded : [];
        }
    }

    /**
     * Capture current HTTP request from superglobals.
     */
    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Path — strip query string and decode
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        // Normalize: remove double slashes
        $path = preg_replace('#/+#', '/', $path);

        $query = $_GET;

        // Headers — normalize to lowercase with hyphens
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        // CONTENT_TYPE and CONTENT_LENGTH are not prefixed with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Bearer token from Authorization header (also check REDIRECT_HTTP_AUTHORIZATION for some cPanel configs)
        if (!isset($headers['authorization'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';
            if ($auth !== '') {
                $headers['authorization'] = $auth;
            }
        }

        // Client IP (handle trusted proxies)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Raw body
        $rawBody = file_get_contents('php://input') ?: '';

        return new self($method, $path, $query, $rawBody, $headers, $ip);
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /** Get URL route parameter (e.g. {ref}). */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** Get query parameter. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** Check if input, query or param key exists. */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body)
            || array_key_exists($key, $this->query)
            || array_key_exists($key, $this->params);
    }

    /** Get a body field. Dot notation NOT supported (keep it simple). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Get entire parsed body array. */
    public function all(): array
    {
        return $this->body;
    }

    /** Get raw (unparsed) body string. */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** Get a header value (case-insensitive). */
    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** Get all headers (lowercased keys). */
    public function headers(): array
    {
        return $this->headers;
    }

    /** Extract Bearer token from Authorization header. Returns '' if absent. */
    public function bearerToken(): string
    {
        $auth = $this->header('authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return '';
    }

    public function clientIp(): string
    {
        return $this->clientIp;
    }

    public function isJson(): bool
    {
        return str_contains($this->header('content-type'), 'application/json');
    }

    public function isPublicWebhook(): bool
    {
        return str_starts_with($this->path, '/webhooks/');
    }

    /** Check if path starts with the given prefix (for surface detection). */
    public function surface(): string
    {
        if (str_starts_with($this->path, '/super')) return 'super';
        if (str_starts_with($this->path, '/admin')) return 'admin';
        if (str_starts_with($this->path, '/sales')) return 'sales';
        if (str_starts_with($this->path, '/portal')) return 'portal';
        return 'api';
    }
}
