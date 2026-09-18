<?php
namespace BBS\Core;

class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $headers;
    private ?\stdClass $user = null;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = array_merge($_POST, $this->getJsonInput());
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    private function getJsonInput(): array
    {
        $contentType = $this->header('Content-Type', '');
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            return is_array($data) ? $data : [];
        }
        // Handle form-data for PUT/PATCH/DELETE
        $method = $this->method();
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            parse_str(file_get_contents('php://input'), $data);
            return $data;
        }
        return [];
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $this->server['CONTENT_TYPE'];
        }
        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['CONTENT-LENGTH'] = $this->server['CONTENT_LENGTH'];
        }
        return $headers;
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Support method override via _method field or X-HTTP-Method-Override header
        if ($method === 'POST') {
            if ($override = $this->input('_method')) {
                return strtoupper($override);
            }
            if ($override = $this->header('X-HTTP-Method-Override')) {
                return strtoupper($override);
            }
        }
        return $method;
    }

    public function path(): string
    {
        $uri = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $basePath = dirname($this->server['SCRIPT_NAME'] ?? '/');
        if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        return '/' . trim($uri, '/');
    }

    public function url(): string
    {
        $scheme = $this->isHttps() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}" . $this->path();
    }

    public function fullUrl(): string
    {
        return $this->url() . ($this->queryString() ? '?' . $this->queryString() : '');
    }

    public function queryString(): string
    {
        return $this->server['QUERY_STRING'] ?? '';
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? 80) == 443;
    }

    public function input(string $key, $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }
        return $data;
    }

    public function except(array $keys): array
    {
        $data = $this->all();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function query(string $key, $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function header(string $key, $default = null): ?string
    {
        $key = strtoupper(str_replace('-', '-', $key));
        return $this->headers[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function ip(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $key) {
            if (!empty($this->server[$key])) {
                $ips = explode(',', $this->server[$key]);
                return trim($ips[0]);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function user(): ?\stdClass
    {
        return $this->user;
    }

    public function setUser(\stdClass $user): void
    {
        $this->user = $user;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return strpos($accept, 'application/json') !== false || $this->isAjax();
    }

    public function scheme(): string
    {
        return $this->isHttps() ? 'https' : 'http';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? 'localhost';
    }

    public function root(): string
    {
        return "{$this->scheme()}://{$this->host()}";
    }

    public function session(): Session
    {
        return Session::getInstance();
    }

    public function validate(array $rules): array
    {
        $validator = new Validator($this->all(), $rules);
        if ($validator->fails()) {
            Response::json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
            exit;
        }
        return $validator->validated();
    }
}
