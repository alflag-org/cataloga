<?php

declare(strict_types=1);

namespace Cataloga\Http;

final class Response
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
    }

    public static function html(string $body, int $statusCode = 200): self
    {
        return new self($statusCode, $body);
    }

    public static function json(array $payload, int $statusCode = 200): self
    {
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self(
            $statusCode,
            $body === false ? '{}' : $body,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self($statusCode, '', ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
