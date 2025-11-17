<?php

declare(strict_types=1);

use App\Http\JsonResponse;
use App\Services\FilmService;
use App\Support\Request;

require __DIR__ . '/bootstrap.php';

$request = new Request();
$service = new FilmService();
$action = $request->input('action', 'discover');
$filters = $request->all();
unset($filters['action']);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    switch ($action) {
        case 'metadata':
            $metadata = $service->metadata();
            JsonResponse::send([
                'providers' => $metadata['providers'],
                'genres' => $metadata['genres'],
            ]);
            break;

        case 'highlights':
            JsonResponse::send($service->highlight($filters));
            break;

        case 'discover':
        default:
            JsonResponse::send($service->listMovies($filters));
            break;
    }
} catch (Throwable $exception) {
    JsonResponse::send([
        'error' => true,
        'message' => $exception->getMessage(),
    ], 500);
}
