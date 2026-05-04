<?php

declare(strict_types=1);

namespace Cataloga\Http;

final class Request
{
    private array $jsonBody = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly string $rawBody,
    ) {
        $this->jsonBody = $this->parseJsonBody();
    }

    public static function fromGlobals(): self
    {
        $rawPath = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($rawPath, PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            file_get_contents('php://input') ?: ''
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }

        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        return $default;
    }

    public function all(): array
    {
        if ($this->isJson()) {
            return $this->jsonBody;
        }

        return $this->post;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }

        return null;
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';

        return str_contains(strtolower((string) $contentType), 'application/json');
    }

    public function isApiRequest(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    private function parseJsonBody(): array
    {
        if (!$this->isJson()) {
            return [];
        }

        if (trim($this->rawBody) === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}
