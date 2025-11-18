    private function providerCounts(array $filters): array
    {
        $cacheKey = 'provider_counts_' . md5(json_encode($filters));
        $ttl = (int) Config::get('cache.ttl.discover', 1800);

        return $this->cache->remember($cacheKey, $ttl, function () use ($filters) {
            $counts = [];
            foreach ($this->providers as $key => $provider) {
                $providerFilters = $filters;
                $providerFilters['providers'] = [$key];
                $providerFilters['page'] = 1;
                try {
                    $discover = $this->tmdb->discoverMovies($providerFilters);
                    $counts[$key] = (int) ($discover['total_results'] ?? 0);
                } catch (RuntimeException $exception) {
                    $counts[$key] = 0;
                }
            }
            return $counts;
        });
    }
