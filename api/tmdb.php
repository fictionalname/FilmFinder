<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const DATA_DIR = __DIR__ . '/../data';
const METADATA_FILE = DATA_DIR . '/metadata.json';
const AGGREGATE_FILE = DATA_DIR . '/films.json';
const GENRE_FILE = DATA_DIR . '/genres.json';
const CACHE_TTL = 86400;
const TMDB_API_KEY = '38c31b9e3fdcee3911223b37b415fbbc';
const TMDB_READ_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiIzOGMzMWI5ZTNmZGNlZTM5MTEyMjNiMzdiNDE1ZmJiYyIsIm5iZiI6MTc2MzEzODIwNi40MDMsInN1YiI6IjY5MTc1YTllNTViMzRmNzM0ZmEwZTMzMCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.tWQKxro1RSbET_vKHaPDifqFmJZ2ZWayBi6_p3K-mgY';
const TMDB_BASE = 'https://api.themoviedb.org';
const PROVIDERS = [
    8 => 'Netflix',
    9 => 'Amazon',
    337 => 'Disney',
    350 => 'Apple',
];
const WATCH_REGION = 'GB';
const MIN_CHUNK_SIZE = 20;
const MAX_CHUNK_SIZE = 1000;
const MAX_CHUNK_PAGES = 60;

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

$action = $_GET['action'] ?? 'status';

switch ($action) {
    $providerId = isset($_GET['provider']) ? (int)$_GET['provider'] : null;
    if (!$providerId || !isset(PROVIDERS[$providerId])) {
        respond(['error' => 'Invalid provider id'], 400);
    }
    $chunkSize = isset($_GET['chunkSize']) ? (int)$_GET['chunkSize'] : 1000;
    respond(handleChunk($providerId, $chunkSize));
    break;
        $providerId = isset($_GET['provider']) ? (int)$_GET['provider'] : null;
        if (!$providerId || !isset(PROVIDERS[$providerId])) {
            respond(['error' => 'Invalid provider id'], 400);
        }
        $chunkSize = max(1, min(5, (int)($_GET['chunkSize'] ?? 1)));
        respond(handleChunk($providerId, $chunkSize));
        break;
    case 'films':
        respond(getAggregatedResponse());
        break;
    case 'status':
    default:
        respond(getStatusResponse());
        break;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function getMetadata(): array
{
    if (!file_exists(METADATA_FILE)) {
        return initializeMetadata();
    }
    $raw = json_decode(file_get_contents(METADATA_FILE), true);
    if (!is_array($raw)) {
        return initializeMetadata();
    }
    // ensure all providers exist
    foreach (PROVIDERS as $id => $name) {
        if (!isset($raw['providers'][$id])) {
            $raw['providers'][$id] = createProviderMeta($id);
        }
    }
    return $raw;
}

function saveMetadata(array $data): void
{
    file_put_contents(METADATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function initializeMetadata(): array
{
    $meta = ['providers' => [], 'lastCacheRefresh' => 0];
    foreach (PROVIDERS as $id => $name) {
        $meta['providers'][$id] = createProviderMeta($id);
    }
    saveMetadata($meta);
    return $meta;
}

function createProviderMeta(int $id): array
{
    return [
        'id' => $id,
        'name' => PROVIDERS[$id],
        'lastFetched' => 0,
        'nextPage' => 1,
        'totalPages' => null,
        'completed' => false,
        'latestReleaseDate' => null,
        'seen_ids' => [],
        'needsRefresh' => true,
    ];
}

function getAggregated(): array
{
    if (!file_exists(AGGREGATE_FILE)) {
        return ['movies' => [], 'lastUpdated' => 0];
    }
    $raw = json_decode(file_get_contents(AGGREGATE_FILE), true);
    if (!is_array($raw) || !isset($raw['movies'])) {
        return ['movies' => [], 'lastUpdated' => 0];
    }
    return $raw;
}

function saveAggregated(array $payload): void
{
    file_put_contents(AGGREGATE_FILE, json_encode($payload, JSON_PRETTY_PRINT));
}

function getGenreMap(): array
{
    if (file_exists(GENRE_FILE)) {
        $content = json_decode(file_get_contents(GENRE_FILE), true);
        if (isset($content['timestamp']) && (time() - $content['timestamp'] < CACHE_TTL) && isset($content['map'])) {
            return $content['map'];
        }
    }
    $response = tmdbRequest('/3/genre/movie/list', ['language' => 'en-US']);
    if (!$response || empty($response['genres'])) {
        return [];
    }
    $map = [];
    foreach ($response['genres'] as $genre) {
        $map[(int)$genre['id']] = $genre['name'];
    }
    file_put_contents(GENRE_FILE, json_encode(['timestamp' => time(), 'map' => $map], JSON_PRETTY_PRINT));
    return $map;
}

function getStatusResponse(): array
{
    $metadata = getMetadata();
    $agg = getAggregated();
    $movies = $agg['movies'] ?? [];
    $overallTotal = count($movies);
    $providerSnapshot = [];
    foreach ($metadata['providers'] as $providerId => $meta) {
        $providerSnapshot[$providerId] = buildProviderSnapshot($meta, $movies);
    }
    return [
        'overall' => [
            'totalCached' => $overallTotal,
            'uniqueMovies' => $overallTotal,
            'lastUpdated' => $agg['lastUpdated'] ?? 0,
        ],
        'providers' => array_values($providerSnapshot),
        'cacheFreshness' => $metadata['lastCacheRefresh'] ?? 0,
    ];
}

function buildProviderSnapshot(array $meta, array $movies): array
{
    $count = 0;
    foreach ($movies as $movie) {
        if (isset($movie['provider_ids']) && in_array($meta['id'], $movie['provider_ids'], true)) {
            $count++;
        }
    }
    $needsRefresh = !$meta['completed'] || (time() - $meta['lastFetched'] > CACHE_TTL);
    return [
        'id' => $meta['id'],
        'name' => $meta['name'],
        'cached' => $count,
        'completed' => (bool)$meta['completed'],
        'totalPages' => $meta['totalPages'],
        'nextPage' => $meta['nextPage'],
        'lastFetched' => $meta['lastFetched'],
        'latestReleaseDate' => $meta['latestReleaseDate'],
        'needsRefresh' => $needsRefresh,
    ];
}

function getAggregatedResponse(): array
{
    $agg = getAggregated();
    return [
        'movies' => $agg['movies'],
        'lastUpdated' => $agg['lastUpdated'],
    ];
}

function handleChunk(int $providerId, int $chunkSize): array
{
    $metadata = getMetadata();
    $providerMeta = &$metadata['providers'][$providerId];

    $chunkSize = max(MIN_CHUNK_SIZE, min(MAX_CHUNK_SIZE, $chunkSize));
    $now = time();
    $ttlExpired = ($now - ($providerMeta['lastFetched'] ?? 0)) > CACHE_TTL;
    $needsUpdate = !$providerMeta['completed'] || $ttlExpired;
    if (!$needsUpdate) {
        return [
            'provider' => buildProviderSnapshot($providerMeta, getAggregated()['movies']),
            'overall' => [
                'cachedMovies' => count(getAggregated()['movies']),
            ],
            'message' => 'Provider cache is fresh.',
        ];
    }

    $genreMap = getGenreMap();
    $aggregate = getAggregated();
    $movies = $aggregate['movies'];
    $seenIds = array_flip($providerMeta['seen_ids'] ?? []);
    $totalPages = $providerMeta['totalPages'];
    $page = max(1, $providerMeta['nextPage'] ?? 1);
    $newAdded = 0;
    $duplicateStreak = 0;
    $currentYear = (int)date('Y');
    $stopEarly = false;
    $processedMovies = 0;
    $pagesFetched = 0;

    while (true) {
        if ($pagesFetched >= MAX_CHUNK_PAGES) {
            break;
        }
        $query = [
            'with_watch_providers' => $providerId,
            'watch_region' => WATCH_REGION,
            'sort_by' => 'primary_release_date.desc',
            'page' => $page,
            'primary_release_date.gte' => '2020-01-01',
            'primary_release_date.lte' => $currentYear . '-12-31',
            'api_key' => TMDB_API_KEY,
        ];
        $response = tmdbRequest('/3/discover/movie', $query);
        if (!$response || empty($response['results'])) {
            break;
        }
        $pagesFetched++;
        $processedMovies += count($response['results']);
        if (empty($totalPages)) {
            $totalPages = $response['total_pages'] ?? null;
        }
        foreach ($response['results'] as $movieData) {
            $releaseDate = $movieData['release_date'] ?? '';
            if ($releaseDate) {
                $year = (int)substr($releaseDate, 0, 4);
                if ($year < 2020) {
                    $stopEarly = true;
                    break;
                }
                if ($year > $currentYear) {
                    continue;
                }
            }
            $movieId = $movieData['id'];
            if (isset($seenIds[$movieId])) {
                $duplicateStreak++;
                if ($duplicateStreak >= 5) {
                    $stopEarly = true;
                    break;
                }
                continue;
            }
            $duplicateStreak = 0;
            $cast = fetchTopCast($movieId);
            $movieRecord = buildMovieRecord($movieData, $genreMap, $providerId, $cast);
            if (upsertMovie($movies, $movieRecord, $providerId)) {
                $newAdded++;
                $seenIds[$movieId] = true;
                $providerMeta['seen_ids'][] = $movieId;
                if (!empty($releaseDate)) {
                    if (empty($providerMeta['latestReleaseDate']) || $releaseDate > $providerMeta['latestReleaseDate']) {
                        $providerMeta['latestReleaseDate'] = $releaseDate;
                    }
                }
            }
        }
        if ($stopEarly || $processedMovies >= $chunkSize) {
            break;
        }
        $page++;
        if ($totalPages !== null && $page > $totalPages) {
            $stopEarly = true;
            break;
        }
    }

    if ($stopEarly || ($totalPages !== null && $page > $totalPages)) {
        $providerMeta['completed'] = true;
    }
    $providerMeta['nextPage'] = $page;
    $providerMeta['totalPages'] = $totalPages;
    $providerMeta['lastFetched'] = $now;
    $metadata['lastCacheRefresh'] = $now;
    $aggregate['movies'] = $movies;
    $aggregate['lastUpdated'] = $now;
    saveAggregated($aggregate);
    saveMetadata($metadata);

    return [
        'provider' => buildProviderSnapshot($providerMeta, $movies),
        'overall' => [
            'cachedMovies' => count($movies),
            'newAdded' => $newAdded,
        ],
        'toast' => $newAdded > 0 ? [
            'providerId' => $providerId,
            'providerName' => PROVIDERS[$providerId],
            'added' => $newAdded,
        ] : null,
    ];
}

function tmdbRequest(string $endpoint, array $query = []): ?array
{
    $url = TMDB_BASE . $endpoint;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_READ_TOKEN,
        'Content-Type: application/json;charset=utf-8',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status >= 400) {
        return null;
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }
    return $data;
}

function fetchTopCast(int $movieId): array
{
    $response = tmdbRequest("/3/movie/$movieId/credits", ['api_key' => TMDB_API_KEY]);
    if (!$response || empty($response['cast'])) {
        return [];
    }
    $cast = array_slice($response['cast'], 0, 5);
    $names = [];
    foreach ($cast as $person) {
        $names[] = $person['name'] ?? '';
    }
    return array_filter($names);
}

function buildMovieRecord(array $movieData, array $genreMap, int $providerId, array $cast): array
{
    $genreList = [];
    foreach ($movieData['genre_ids'] ?? [] as $genreId) {
        if (isset($genreMap[$genreId])) {
            $genreList[] = [
                'id' => $genreId,
                'name' => $genreMap[$genreId],
            ];
        }
    }
    $releaseDate = $movieData['release_date'] ?? '';
    return [
        'id' => $movieData['id'],
        'title' => $movieData['title'] ?? '',
        'release_date' => $releaseDate,
        'year' => $releaseDate ? substr($releaseDate, 0, 4) : '',
        'overview' => $movieData['overview'] ?? '',
        'vote_average' => $movieData['vote_average'] ?? 0,
        'vote_count' => $movieData['vote_count'] ?? 0,
        'poster_path' => $movieData['poster_path'] ?? null,
        'genres' => $genreList,
        'cast' => $cast,
        'provider_ids' => [$providerId],
        'providers' => [
            [
                'id' => $providerId,
                'name' => PROVIDERS[$providerId],
            ],
        ],
        'tmdb_url' => "https://www.themoviedb.org/movie/{$movieData['id']}",
    ];
}

function upsertMovie(array &$movies, array $newMovie, int $providerId): bool
{
    foreach ($movies as &$movie) {
        if ($movie['id'] === $newMovie['id']) {
            if (!in_array($providerId, $movie['provider_ids'], true)) {
                $movie['provider_ids'][] = $providerId;
                $movie['providers'][] = ['id' => $providerId, 'name' => PROVIDERS[$providerId]];
            }
            return false;
        }
    }
    $movies[] = $newMovie;
    return true;
}
