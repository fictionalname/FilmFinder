<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    private array $query;
    private array $body;
    private array $server;

    public function __construct()
    {
        $this->query = $_GET ?? [];
        $this->body = $this->parseBody();
        $this->server = $_SERVER ?? [];
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        parse_str($raw, $parsed);

        return is_array($parsed) ? $parsed : [];
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        return $default;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
}
