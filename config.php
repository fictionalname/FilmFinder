<?php

return [
    'app' => [
        'name' => 'Film Finder',
        'env' => 'production',
        'version' => '2025.11.17-01',
        'timezone' => 'Europe/London',
        'region' => 'GB',
        'default_language' => 'en-GB',
    ],
    'fonts' => [
        'heading' => '"Space Grotesk", "Sora", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'body' => '"Sora", "Space Grotesk", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    ],
    'tmdb' => [
        'api_key' => '38c31b9e3fdcee3911223b37b415fbbc',
        'read_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiIzOGMzMWI5ZTNmZGNlZTM5MTEyMjNiMzdiNDE1ZmJiYyIsIm5iZiI6MTc2MzEzODIwNi40MDMsInN1YiI6IjY5MTc1YTllNTViMzRmNzM0ZmEwZTMzMCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.tWQKxro1RSbET_vKHaPDifqFmJZ2ZWayBi6_p3K-mgY',
        'base_url' => 'https://api.themoviedb.org/3',
        'timeout' => 15,
        'default_params' => [
            'watch_region' => 'GB',
            'language' => 'en-GB',
        ],
    ],
    'providers' => [
        'netflix' => [
            'id' => 8,
            'label' => 'Netflix',
            'color' => '#e50914',
        ],
        'amazon' => [
            'id' => 10,
            'label' => 'Amazon',
            'color' => '#00a8e1',
        ],
        'disney' => [
            'id' => 337,
            'label' => 'Disney',
            'color' => '#113ccf',
        ],
        'apple' => [
            'id' => 350,
            'label' => 'Apple',
            'color' => '#f5f5f7',
        ],
    ],
    'cache' => [
        'path' => __DIR__ . '/storage/cache',
        'ttl' => [
            'discover' => 1800, // 30 minutes
            'metadata' => 86400, // 24 hours
            'highlights' => 1200, // 20 minutes
        ],
    ],
];
