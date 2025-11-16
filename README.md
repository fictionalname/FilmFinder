# Streaming Film Explorer

This is a mobile-first streaming film discovery experience that caches TMDB data via a PHP backend. The frontend requests only cached data and renders a responsive filter panel, film grid, and live download overlay with chunked updates for Netflix, Amazon, Disney, and Apple while targeting the UK region.

## Features

- Provider-specific chunked downloads (up to 100 films per chunk) with cast details fetched during each chunk.
- Cache invalidation after 24 hours and only the missing films are appended to the cache.
- Film cards include title, release year, summary, TMDB rating, vote count, cast, genres, available services, and quick links.
- Filters for services (OR logic), year range, genre tick boxes (OR logic), and text search plus sorting.
- Live summary stats, progress overlay, and toast notifications for each provider chunk.
- Local disk caching in `data/` to avoid exposing TMDB keys to the browser.

## Backend (`api/tmdb.php`)

1. Uses TMDB v4 bearer token authentication for all requests.
2. Stores metadata at `data/metadata.json` to track chunked downloads, seen movie IDs, and cache freshness.
3. Caches aggregated movie data at `data/films.json` (includes cast, genres, providers, TMDB links).
4. Supports three actions:
   - `action=status`: returns provider download state plus overall counts.
   - `action=chunk`: fetches the next chunk for a given provider and returns realtime stats.
   - `action=films`: exposes the deduplicated movie list for the frontend.
5. Genre map is refreshed automatically and saved under `data/genres.json`.

## Frontend (`index.html`, CSS, JS)

- Hosted assets live in the root with styles under `css/styles.css` and logic in `js/app.js`.
- Loading overlay shows provider progress, film counts, and a progress bar. It stays visible until the cache is synchronized.
- Filters overlay sticks to the top of the experience on mobile, keeping service chips, year dropdowns, search input, sort select, and a neatly aligned genre grid packed tight above the scrolling film grid.
- Summary card reports how many films are cached and provides per-provider counts.
- Film grid displays poster thumbnails (~80px wide), cast (top 5 names), genres, services, and quick links to TMDB and YouTube trailers.
- Toast area surfaces provider-specific cache additions in realtime.

## Troubleshooting

- If a chunk fails with HTTP 500, inspect `data/tmdb.log` for curl status, TMDB response codes, and backend exceptions; every problematic request is written there with timestamps.

## Running locally

1. Serve the directory with PHP’s built-in server (or any web server that routes `api/tmdb.php` requests):
   ```bash
   php -S localhost:8000
   ```
2. Point your browser to `http://localhost:8000`.
3. On the first visit, the overlay will download chunked data for each provider and cache it under `data/`. Subsequent visits will use the cached dataset until 24 hours elapse.

## Cache behavior

- All TMDB data is stored in `data/` and expires after 24 hours (per metadata timestamps).
- Subsequent chunk downloads only fetch movies that aren’t already recorded (the backend tracks seen IDs per provider).
- Each chunk fetch includes the film’s cast details, so the frontend never needs to call TMDB directly.
- Toasts display how many new films were added per provider during a download run.

## Notes

- Keep the PHP `data/` directory writable so the cache and metadata can be updated.
- No API keys are ever exposed in the browser; all TMDB communication happens server-side.

