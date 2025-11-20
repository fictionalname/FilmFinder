<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class HitCounter
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $defaultPath = Config::get('stats.hit_counter_file', __DIR__ . '/../../storage/state/hit-count.txt');
        $this->file = $file ?? $defaultPath;
    }

    public function increment(): int
    {
        $this->ensureDirectory();
        $handle = @fopen($this->file, 'c+');
        if ($handle === false) {
            return $this->current();
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock hit counter file.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $current = $this->parseCount($contents);
            $next = $current + 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $next);
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);

            return $next;
        } catch (RuntimeException $exception) {
            fclose($handle);
            return $this->current();
        }
    }

    public function current(): int
    {
        if (!is_file($this->file)) {
            return 0;
        }

        $contents = @file_get_contents($this->file);
        if ($contents === false) {
            return 0;
        }

        return $this->parseCount($contents);
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }

    private function parseCount(string|false $contents): int
    {
        if ($contents === false) {
            return 0;
        }
        $value = trim($contents);
        if ($value === '') {
            return 0;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);
        return is_int($number) ? max(0, $number) : 0;
    }
}
