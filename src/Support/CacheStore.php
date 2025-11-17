<?php

declare(strict_types=1);

namespace App\Support;

final class CacheStore
{
    private string $path;
    private bool $enabled = true;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? Config::get('cache.path', sys_get_temp_dir());
        if (!is_dir($this->path)) {
            $created = @mkdir($this->path, 0775, true);
            if (!$created && !is_dir($this->path)) {
                $this->enabled = false;
            }
        }
    }

    private function keyToFile(string $key): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sha1($key) . '.cache.php';
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $cached = $this->get($key);

        if ($cached !== null && !$this->isExpired($cached)) {
            return $cached['value'];
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function get(string $key): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->keyToFile($key);

        if (!file_exists($file)) {
            return null;
        }

        $payload = @include $file;
        if (!is_array($payload) || !isset($payload['value'], $payload['expires_at'])) {
            return null;
        }

        return $payload;
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        if (!$this->enabled) {
            return;
        }

        $file = $this->keyToFile($key);
        $expiresAt = time() + $ttl;

        $payload = var_export([
            'value' => $value,
            'expires_at' => $expiresAt,
        ], true);

        $php = "<?php return {$payload};";
        @file_put_contents($file, $php, LOCK_EX);
    }

    private function isExpired(array $payload): bool
    {
        return ($payload['expires_at'] ?? 0) < time();
    }
}
