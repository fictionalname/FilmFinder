<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheStore;
use App\Support\Config;
use RuntimeException;

final class FilmService
{
    private TmdbClient $tmdb;
    private CacheStore $cache;
    private array $providers;
    private array $genreMap = [];

    public function __construct(?TmdbClient $tmdb = null, ?CacheStore $cache = null)
    {
        $this->tmdb = $tmdb ?? new TmdbClient();
        $this->cache = $cache ?? new CacheStore();
        $this->providers = Config::get('providers', []);
    }

    public function metadata(): array
    {
        return [
            'providers' => $this->providers,
            'genres' => $this->getGenreMap(true),
        ];
    }

    public function listMovies(array $inputFilters): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        if (count($filters['providers']) === 0) {
            return $this->emptyListResponse($filters);
        }
        if (!empty($filters['query'])) {
            return $this->buildSearchResponse($filters);
        }

        $collection = $this->collectMoviesForProviders($filters);
        $sortedEntries = $this->sortAggregatedEntries($collection['entries'], $filters['sort']);
        $movies = [];

        foreach ($sortedEntries as $entry) {
            $formatted = $this->formatMovie($entry['movie'], $filters['providers'], $entry['host_providers']);
            if (!$this->hasProviderAvailability($formatted, $filters['providers'])) {
                continue;
            }
            $movies[] = $formatted;
        }

        $currentPage = max(1, (int) $filters['page']);

        return [
            'results' => $movies,
            'pagination' => [
                'page' => $currentPage,
                'total_pages' => $collection['total_pages'],
                'total_results' => $collection['total_results'],
            ],
            'providers' => [
                'summary' => $this->providerSummary($movies, $collection['provider_totals']),
                'selected' => $filters['providers'],
                'available' => $this->providers,
            ],
            'filters' => [
                'applied' => $filters,
            ],
        ];
    }

    public function highlight(array $inputFilters): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        if (count($filters['providers']) === 0) {
            return [
                'results' => [],
                'filters' => $filters,
            ];
        }
        $filters['sort'] = 'vote_average.desc';
        $filters['vote_count_gte'] = 300;

        $eighteenMonthsAgo = (new \DateTimeImmutable('-18 months'))->format('Y-m-d');
        $filters['start_date'] = max($filters['start_date'] ?? $eighteenMonthsAgo, $eighteenMonthsAgo);

        $ttl = (int) Config::get('cache.ttl.highlights', 1200);
        $cacheKey = 'highlights_' . md5(json_encode($filters));

        $payload = $this->cache->remember($cacheKey, $ttl, function () use ($filters) {
            $collection = $this->collectMoviesForProviders($filters);
            $sortedEntries = $this->sortAggregatedEntries($collection['entries'], $filters['sort']);
            $movies = [];

            foreach ($sortedEntries as $entry) {
                $formatted = $this->formatMovie($entry['movie'], $filters['providers'], $entry['host_providers']);
                if (!$this->hasProviderAvailability($formatted, $filters['providers'])) {
                    continue;
                }
                $movies[] = $formatted;
                if (count($movies) >= 3) {
                    break;
                }
            }

            return $movies;
        });

        return [
            'results' => $payload,
            'filters' => $filters,
        ];
    }

    private function collectMoviesForProviders(array $filters): array
    {
        $entries = [];
        $providerTotals = [];
        $maxTotalPages = 1;
        $totalResults = 0;

        foreach ($filters['providers'] as $providerKey) {
            $discover = $this->tmdb->discoverMoviesForProvider($providerKey, $filters);
            $providerTotals[$providerKey] = (int) ($discover['total_results'] ?? 0);
            $maxTotalPages = max($maxTotalPages, (int) ($discover['total_pages'] ?? 1));
            $totalResults += $providerTotals[$providerKey];

            foreach ($discover['results'] ?? [] as $movie) {
                $movieId = (int) ($movie['id'] ?? 0);
                if ($movieId === 0) {
                    continue;
                }

                if (!isset($entries[$movieId])) {
                    $entries[$movieId] = [
                        'movie' => $movie,
                        'host_providers' => [],
                    ];
                }

                if (!in_array($providerKey, $entries[$movieId]['host_providers'], true)) {
                    $entries[$movieId]['host_providers'][] = $providerKey;
                }
            }
        }

        return [
            'entries' => array_values($entries),
            'provider_totals' => $providerTotals,
            'total_pages' => (int) max(1, $maxTotalPages),
            'total_results' => (int) max($totalResults, count($entries)),
        ];
    }

    private function sortAggregatedEntries(array $entries, string $sort): array
    {
        if (count($entries) <= 1) {
            return $entries;
        }

        [$field, $direction] = array_pad(explode('.', $sort), 2, 'desc');
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        usort($entries, function (array $left, array $right) use ($field, $direction) {
            return $this->compareMovies($left['movie'], $right['movie'], $field, $direction);
        });

        return $entries;
    }

    private function compareMovies(array $left, array $right, string $field, string $direction): int
    {
        $valueLeft = $this->extractSortValue($left, $field);
        $valueRight = $this->extractSortValue($right, $field);

        if ($valueLeft === $valueRight) {
            return 0;
        }

        $result = $valueLeft <=> $valueRight;

        return $direction === 'asc' ? $result : $result * -1;
    }

    private function extractSortValue(array $movie, string $field): float
    {
        switch ($field) {
            case 'primary_release_date':
                $date = $movie['release_date'] ?? null;
                return $date ? (float) strtotime($date) : 0.0;
            case 'vote_average':
                return (float) ($movie['vote_average'] ?? 0);
            case 'vote_count':
                return (float) ($movie['vote_count'] ?? 0);
            case 'popularity':
            default:
                return (float) ($movie['popularity'] ?? 0);
        }
    }

    private function buildSearchResponse(array $filters): array
    {
        $discover = $this->tmdb->discoverMovies($filters);
        $results = $discover['results'] ?? [];
        $movies = [];

        foreach ($results as $movie) {
            $formatted = $this->formatMovie($movie, $filters['providers']);
            if (!$this->hasProviderAvailability($formatted, $filters['providers'])) {
                continue;
            }
            $movies[] = $formatted;
        }

        return [
            'results' => $movies,
            'pagination' => [
                'page' => $discover['page'] ?? $filters['page'],
                'total_pages' => $discover['total_pages'] ?? 1,
                'total_results' => $discover['total_results'] ?? count($movies),
            ],
            'providers' => [
                'summary' => $this->providerSummary($movies),
                'selected' => $filters['providers'],
                'available' => $this->providers,
            ],
            'filters' => [
                'applied' => $filters,
            ],
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $availableProviders = array_keys($this->providers);
        $selectedProviders = [];
        if (!array_key_exists('providers', $filters)) {
            $selectedProviders = $availableProviders;
        } else {
            $providersParam = $filters['providers'];
            if (is_string($providersParam)) {
                $providersParam = array_filter(array_map('trim', explode(',', $providersParam)));
            }
            if (!is_array($providersParam)) {
                $providersParam = [];
            }
            $selectedProviders = array_values(array_intersect($availableProviders, $providersParam));
        }

        $genres = $filters['genres'] ?? [];
        if (is_string($genres)) {
            $genres = array_filter(array_map('trim', explode(',', $genres)));
        }
        $genres = array_values(array_unique(array_filter($genres, static fn ($id) => is_numeric($id))));

        $excludeGenres = $filters['exclude_genres'] ?? [];
        if (is_string($excludeGenres)) {
            $excludeGenres = array_filter(array_map('trim', explode(',', $excludeGenres)));
        }
        $excludeGenres = array_values(array_unique(array_filter($excludeGenres, static fn ($id) => is_numeric($id))));
        $excludeGenres = array_values(array_diff($excludeGenres, $genres));

        $allowedSorts = [
            'popularity.desc',
            'popularity.asc',
            'primary_release_date.desc',
            'primary_release_date.asc',
            'vote_average.desc',
            'vote_count.desc',
        ];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'popularity.desc';

        $page = max(1, min(500, (int) ($filters['page'] ?? 1)));

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $startDate = $this->validateDate($startDate) ?? null;
        $endDate = $this->validateDate($endDate) ?? null;

        $query = trim((string) ($filters['query'] ?? ''));

        return [
            'providers' => $selectedProviders,
            'genres' => $genres,
            'exclude_genres' => $excludeGenres,
            'sort' => $sort,
            'page' => $page,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'query' => $query !== '' ? $query : null,
            'vote_count_gte' => $filters['vote_count_gte'] ?? null,
        ];
    }

    private function validateDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $dt ? $dt->format('Y-m-d') : null;
    }

    private function formatMovie(array $movie, array $selectedProviders = [], array $hostProviders = []): array
    {
        $movieId = (int) $movie['id'];
        $details = $this->tmdb->movieDetails($movieId);
        $genres = $this->mapGenres($movie['genre_ids'] ?? []);
        $providers = $this->extractProviders($details, $selectedProviders, $hostProviders);
        $recommendations = $this->buildRecommendations($movieId, $selectedProviders);

        return [
            'id' => $movieId,
            'title' => $movie['title'] ?? $movie['name'] ?? 'Untitled',
            'tagline' => $details['tagline'] ?? '',
            'overview' => $movie['overview'] ?? $details['overview'] ?? '',
            'release_date' => $movie['release_date'] ?? null,
            'release_year' => isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : null,
            'runtime' => $details['runtime'] ?? null,
            'genres' => $genres,
            'cast' => $this->extractCast($details),
            'certification' => $this->extractCertification($details),
            'poster_path' => $movie['poster_path'] ?? null,
            'backdrop_path' => $movie['backdrop_path'] ?? null,
            'tmdb_url' => sprintf('https://www.themoviedb.org/movie/%d', $movieId),
            'trailer_url' => sprintf(
                'https://www.youtube.com/results?search_query=%s',
                urlencode(($movie['title'] ?? '') . ' trailer')
            ),
            'ratings' => $this->buildRatings($movie),
            'providers' => $providers,
            'recommendations' => $recommendations,
        ];
    }

    private function buildRatings(array $movie): array
    {
        $tmdbScore = isset($movie['vote_average']) ? round((float) $movie['vote_average'], 1) : null;
        $tmdbCount = $movie['vote_count'] ?? null;
        $imdbScore = $tmdbScore !== null ? round($tmdbScore, 1) : null;
        $rotten = $tmdbScore !== null ? min(100, max(0, (int) round($tmdbScore * 10))) : null;

        return [
            'tmdb' => [
                'score' => $tmdbScore,
                'count' => $tmdbCount,
            ],
            'imdb' => [
                'score' => $imdbScore,
            ],
            'rotten_tomatoes' => [
                'score' => $rotten,
            ],
        ];
    }

    private function mapGenres(array $genreIds): array
    {
        $genreMap = $this->getGenreMap();
        $names = [];

        foreach ($genreIds as $id) {
            if (isset($genreMap[$id])) {
                $names[] = $genreMap[$id];
            }
        }

        return $names;
    }

    private function getGenreMap(bool $asList = false): array
    {
        if (empty($this->genreMap)) {
            $genres = $this->tmdb->genres();
            foreach ($genres as $genre) {
                $this->genreMap[(string) $genre['id']] = $genre['name'];
            }
        }

        if ($asList) {
            $list = [];
            foreach ($this->genreMap as $id => $name) {
                $list[] = ['id' => (int) $id, 'name' => $name];
            }

            return $list;
        }

        return $this->genreMap;
    }

    private function extractCast(array $details, int $limit = 3): array
    {
        $cast = $details['credits']['cast'] ?? [];
        $names = [];

        foreach ($cast as $member) {
            if (!empty($member['name'])) {
                $names[] = $member['name'];
            }
            if (count($names) >= $limit) {
                break;
            }
        }

        return $names;
    }

    private function extractCertification(array $details): ?string
    {
        $countries = $details['release_dates']['results'] ?? [];

        foreach ($countries as $country) {
            if (($country['iso_3166_1'] ?? '') !== Config::get('app.region', 'GB')) {
                continue;
            }

            foreach ($country['release_dates'] as $release) {
                if (!empty($release['certification'])) {
                    return $release['certification'];
                }
            }
        }

        return null;
    }

    private function extractProviders(array $details, array $selectedProviders = [], array $hostProviders = []): array
    {
        $results = $details['watch/providers']['results'] ?? [];
        $region = Config::get('app.region', 'GB');
        $countryProviders = $results[$region] ?? [];
        $buckets = [
            $countryProviders['flatrate'] ?? [],
            $countryProviders['ads'] ?? [],
        ];
        $availableIds = [];
        foreach ($buckets as $bucket) {
            foreach ($bucket as $provider) {
                $availableIds[] = (int) $provider['provider_id'];
            }
        }

        $providers = [];

        foreach ($this->providers as $key => $provider) {
            $isHost = in_array($key, $hostProviders, true);
            $providers[$key] = [
                'label' => $provider['label'],
                'color' => $provider['color'],
                'available' => $isHost ? true : in_array((int) $provider['id'], $availableIds, true),
                'host' => $isHost,
            ];
        }

        $hasMatch = array_reduce($providers, static fn ($carry, $provider) => $carry || !empty($provider['available']), false);
        if (!$hasMatch && count($selectedProviders) === 1 && empty($hostProviders)) {
            foreach ($selectedProviders as $selectedKey) {
                if (isset($providers[$selectedKey])) {
                    $providers[$selectedKey]['available'] = true;
                }
            }
        }

        return $providers;
    }

    private function providerSummary(array $movies, array $overrideTotals = []): array
    {
        $summary = [];
        foreach ($this->providers as $key => $provider) {
            $summary[$key] = [
                'label' => $provider['label'],
                'color' => $provider['color'],
                'count' => 0,
            ];
        }

        if (!empty($overrideTotals)) {
            foreach ($overrideTotals as $key => $count) {
                if (isset($summary[$key])) {
                    $summary[$key]['count'] = (int) $count;
                }
            }

            return $summary;
        }

        foreach ($movies as $movie) {
            foreach ($movie['providers'] as $key => $providerData) {
                if (!empty($providerData['available'])) {
                    $summary[$key]['count']++;
                }
            }
        }

        return $summary;
    }

    private function hasProviderAvailability(array $movie, array $selectedProviders = []): bool
    {
        if (empty($movie['providers'])) {
            return false;
        }

        $targets = $selectedProviders;
        if (count($targets) === 0) {
            $targets = array_keys($this->providers);
        }

        foreach ($targets as $providerKey) {
            $provider = $movie['providers'][$providerKey] ?? null;
            if ($provider && !empty($provider['available'])) {
                return true;
            }
        }
        return false;
    }

    private function emptyListResponse(array $filters): array
    {
        return [
            'results' => [],
            'pagination' => [
                'page' => 1,
                'total_pages' => 1,
                'total_results' => 0,
            ],
            'providers' => [
                'summary' => $this->providerSummary([]),
                'selected' => $filters['providers'],
                'available' => $this->providers,
            ],
            'filters' => [
                'applied' => $filters,
            ],
        ];
    }

    /**
     * Build the "If you like this film..." recommendation payload.
     */
    private function buildRecommendations(int $movieId, array $selectedProviders = [], int $limit = 1): array
    {
        if ($limit <= 0) {
            return [];
        }
        $targets = count($selectedProviders) ? $selectedProviders : array_keys($this->providers);
        $recommendations = $this->tmdb->similarMovies($movieId);
        if (empty($recommendations)) {
            return [];
        }
        $entries = [];

        foreach ($recommendations as $recommendation) {
            if (count($entries) >= $limit) {
                break;
            }
            $recommendationId = (int) ($recommendation['id'] ?? 0);
            if ($recommendationId === 0) {
                continue;
            }
            $details = $this->tmdb->movieDetails($recommendationId);
            $providers = $this->extractProviders($details, [], []);
            $matchedProvider = $this->matchRecommendationProvider($providers, $targets);
            if (!$matchedProvider) {
                continue;
            }
            $entries[] = [
                'id' => $recommendationId,
                'title' => $recommendation['title'] ?? $recommendation['name'] ?? 'Untitled',
                'tmdb_url' => sprintf('https://www.themoviedb.org/movie/%d', $recommendationId),
                'provider' => [
                    'key' => $matchedProvider,
                    'label' => $this->providers[$matchedProvider]['label'] ?? $matchedProvider,
                    'color' => $this->providers[$matchedProvider]['color'] ?? '#ffffff',
                ],
            ];
        }

        return $entries;
    }

    private function matchRecommendationProvider(array $providers, array $priorityProviders): ?string
    {
        $targets = count($priorityProviders) ? $priorityProviders : array_keys($this->providers);
        foreach ($targets as $providerKey) {
            if (!empty($providers[$providerKey]['available'])) {
                return $providerKey;
            }
        }
        return null;
    }

}
