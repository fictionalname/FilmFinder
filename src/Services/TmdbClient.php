<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheStore;
use App\Support\Config;
use RuntimeException;

final class TmdbClient
{
    private CacheStore $cache;
    private string $baseUrl;
    private array $defaultParams;

    public function __construct(?CacheStore $cache = null)
    {
        $this->cache = $cache ?? new CacheStore();
        $this->baseUrl = rtrim(Config::get('tmdb.base_url'), '/');
        $this->defaultParams = Config::get('tmdb.default_params', []);
    }

    /**
     * Perform a discover/movie request while caching results per unique parameter set.
     */
    public function discoverMovies(array $filters): array
    {
        $ttl = (int) Config::get('cache.ttl.discover', 1800);
        if (!empty($filters['query'])) {
            $params = $this->buildSearchParams($filters);
            $cacheKey = 'search_' . md5(json_encode($params));
            return $this->cache->remember($cacheKey, $ttl, function () use ($params) {
                return $this->request('search/movie', $params);
            });
        }

        $params = $this->buildDiscoverParams($filters);
        $providerIds = $this->resolveProviderIds($filters);
        if (!empty($providerIds)) {
            $params['with_watch_providers'] = implode('|', $providerIds);
        }
        $cacheKey = 'discover_' . md5(json_encode($params));

        return $this->cache->remember($cacheKey, $ttl, function () use ($params) {
            return $this->request('discover/movie', $params);
        });
    }

    /**
     * Discover movies for a specific provider to keep per-provider counts accurate.
     */
    public function discoverMoviesForProvider(string $providerKey, array $filters): array
    {
        $providersConfig = Config::get('providers', []);
        if (!isset($providersConfig[$providerKey]['id'])) {
            throw new RuntimeException("Unknown provider key [{$providerKey}] supplied to discoverMoviesForProvider.");
        }

        if (!empty($filters['query'])) {
            throw new RuntimeException('discoverMoviesForProvider does not support search queries.');
        }

        $ttl = (int) Config::get('cache.ttl.discover', 1800);
        $params = $this->buildDiscoverParams($filters);
        $params['with_watch_providers'] = (string) $providersConfig[$providerKey]['id'];
        $cacheKey = sprintf('discover_%s_%s', $providerKey, md5(json_encode($params)));

        return $this->cache->remember($cacheKey, $ttl, function () use ($params) {
            return $this->request('discover/movie', $params);
        });
    }

    /**
     * Fetch genre list (cached for long TTL).
     */
    public function genres(): array
    {
        $ttl = (int) Config::get('cache.ttl.metadata', 86400);
        $cacheKey = 'genres';

        return $this->cache->remember($cacheKey, $ttl, function () {
            $data = $this->request('genre/movie/list', [
                'language' => $this->defaultParams['language'] ?? 'en-GB',
            ]);

            return $data['genres'] ?? [];
        });
    }

    /**
     * Retrieve extended details for a single movie (credits + release certificates).
     */
    public function movieDetails(int $movieId): array
    {
        $ttl = (int) Config::get('cache.ttl.metadata', 86400);
        $cacheKey = 'movie_' . $movieId;

        return $this->cache->remember($cacheKey, $ttl, function () use ($movieId) {
            return $this->request("movie/{$movieId}", [
                'append_to_response' => 'credits,release_dates,watch/providers',
                'language' => $this->defaultParams['language'] ?? 'en-GB',
            ]);
        });
    }

    /**
     * Fetch similar movies for the supplied movie ID.
     */
    public function similarMovies(int $movieId): array
    {
        $ttl = (int) Config::get('cache.ttl.metadata', 1800);
        $cacheKey = 'similar_' . $movieId;

        return $this->cache->remember($cacheKey, $ttl, function () use ($movieId) {
            $data = $this->request("movie/{$movieId}/similar", [
                'language' => $this->defaultParams['language'] ?? 'en-GB',
                'page' => 1,
            ]);

            return $data['results'] ?? [];
        });
    }

    /**
     * Build TMDB discover params using filters from the client.
     */
    private function buildDiscoverParams(array $filters): array
    {
        $params = array_merge($this->defaultParams, [
            'include_adult' => 'false',
            'include_video' => 'false',
            'with_watch_monetization_types' => 'flatrate|ads',
            'sort_by' => $filters['sort'] ?? 'popularity.desc',
            'page' => (int) ($filters['page'] ?? 1),
        ]);

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $params['primary_release_date.gte'] = $filters['start_date'] ?? '2000-01-01';
            $params['primary_release_date.lte'] = $filters['end_date'] ?? date('Y-m-d');
        }

        if (!empty($filters['genres']) && is_array($filters['genres'])) {
            $params['with_genres'] = implode('|', array_map('trim', $filters['genres']));
        }

        if (!empty($filters['exclude_genres']) && is_array($filters['exclude_genres'])) {
            $params['without_genres'] = implode('|', array_map('trim', $filters['exclude_genres']));
        }

        if (!empty($filters['vote_count_gte'])) {
            $params['vote_count.gte'] = (int) $filters['vote_count_gte'];
        }

        return $params;
    }

    private function resolveProviderIds(array $filters): array
    {
        $providersConfig = Config::get('providers', []);
        $providerIds = [];

        if (!empty($filters['providers']) && is_array($filters['providers'])) {
            foreach ($filters['providers'] as $requestProvider) {
                if (isset($providersConfig[$requestProvider]['id'])) {
                    $providerIds[] = (string) $providersConfig[$requestProvider]['id'];
                }
            }
        } else {
            $providerIds = array_map(static fn ($provider) => (string) $provider['id'], $providersConfig);
        }

        return $providerIds;
    }

    private function buildSearchParams(array $filters): array
    {
        return [
            'language' => $this->defaultParams['language'] ?? 'en-GB',
            'query' => trim((string) $filters['query']),
            'page' => (int) ($filters['page'] ?? 1),
            'include_adult' => 'false',
            'region' => $this->defaultParams['watch_region'] ?? 'GB',
        ];
    }

    private function request(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        try {
            [$statusCode, $response] = $this->executeCurlRequest($url, $params, $method);
        } catch (RuntimeException $e) {
            [$statusCode, $response] = $this->executeStreamRequest($url, $params, $method);
        }

        $data = json_decode($response, true);

        if ($statusCode >= 400) {
            $message = $data['status_message'] ?? 'Unknown TMDB error';
            throw new RuntimeException("TMDB responded with {$statusCode}: {$message}");
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Unable to decode TMDB response: ' . json_last_error_msg());
        }

        return $data;
    }

    private function executeCurlRequest(string $url, array $params, string $method): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension not available');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) Config::get('tmdb.timeout', 15));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . Config::get('tmdb.read_token'),
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('TMDB request error: ' . $curlError);
        }

        return [$statusCode, (string) $response];
    }

    private function executeStreamRequest(string $url, array $params, string $method): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . Config::get('tmdb.read_token'),
        ];

        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => (int) Config::get('tmdb.timeout', 15),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if ($method === 'POST') {
            $body = json_encode($params);
            $contextOptions['http']['content'] = $body;
            $contextOptions['http']['header'] .= "Content-Type: application/json\r\n";
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException('TMDB stream request error: ' . ($error['message'] ?? 'unknown'));
        }

        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 200';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 200;

        return [$statusCode, (string) $response];
    }
}
