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
        $params = $this->buildDiscoverParams($filters);
        $cacheKey = 'discover_' . md5(json_encode($params));

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
     * Build TMDB discover params using filters from the client.
     */
    private function buildDiscoverParams(array $filters): array
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

        $params = array_merge($this->defaultParams, [
            'with_watch_providers' => implode('|', $providerIds),
            'include_adult' => 'false',
            'include_video' => 'false',
            'sort_by' => $filters['sort'] ?? 'popularity.desc',
            'page' => (int) ($filters['page'] ?? 1),
            'with_origin_country' => 'US|GB',
        ]);

        if (!empty($filters['query'])) {
            $params['with_keywords'] = $filters['query'];
        }

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $params['primary_release_date.gte'] = $filters['start_date'] ?? '2000-01-01';
            $params['primary_release_date.lte'] = $filters['end_date'] ?? date('Y-m-d');
        }

        if (!empty($filters['genres']) && is_array($filters['genres'])) {
            $params['with_genres'] = implode(',', array_map('trim', $filters['genres']));
        }

        return $params;
    }

    private function request(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
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
}
