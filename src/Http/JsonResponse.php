<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function send(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
