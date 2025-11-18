# Film Finder

Modern cinematic web app for UK streaming availability powered by TMDB with a PHP backend, hybrid caching, and a fully remote-filtered client experience.

## Current Status
- Requirements + visual direction captured; typography locked to Space Grotesk (headings) and Sora (body) for consistent futuristic styling.
- Backend foundations complete: bootstrap/config, filesystem cache store, TMDB client wrapper, FilmService (normalisation, runtime/certification/cast enrichment, provider summaries, genre-aware highlights), and a single API entry (`api.php`) exposing discover/metadata/highlights endpoints.
- Frontend experience live: PHP-driven `index.php` with forced refresh/version tokens, compact glass layout, rectangular chips/buttons, year-only release filters, floating Filters FAB/overlay, provider summary chips that double as toggles, highlight row (desktop/tablet only), and grid/list view controls (hidden on mobile).
- A floating glass “status pill” hovers over the content on all breakpoints, summarises provider totals, and expands to reveal each provider’s film count without taking sidebar space.
- Release window filtering only exposes years and now keeps the end year equal to or newer than the start year automatically.
- Genre filters now behave as OR selections so choosing multiple genres surfaces titles matching any of the chosen categories.
- Optional scroll effect: parallax gradients are enabled by default via `data-scroll-effects="enabled"` on `<body>`, producing stronger motion as you scroll; toggle it off by removing or altering the attribute if you prefer a still canvas.
- Mobile filter UX: the floating Filters FAB appears only on narrow screens when the overlay is closed and automatically hides while the sheet is visible, with the top status pill and layout controls hidden on mobile so the film grid has maximal breathing room.
- Client logic implemented (`assets/js/app.js`): metadata loading, filter mirroring across desktop/mobile, multi-genre/provider selection with persistent state, provider counters synced with filter state, query-string + localStorage sync, debounced backend calls, sentinel-driven infinite scroll, genre-aware highlights, provider count updates, empty/loading states, and locally stored recently viewed chips (hidden on small screens).
- Film cards show runtime, BBFC icons pinned over the poster, cast, provider badges, trailer links, and IMDb / Rotten Tomatoes badges derived heuristically from TMDB scores (permitted data source).

## Core Objectives
- Aggregate films currently on Netflix, Amazon, Disney, and Apple (UK region) on demand using TMDB discover endpoints with caching (30–60 min TTL) and no bulk provider scrape.
- Deliver a mobile-first, dark, glassy UI with desktop sidebar filters + mobile overlay, live provider counts, highlight section filtered by genres, rich movie cards, and infinite scroll.
- Keep all TMDB credentials strictly server-side; normalize responses to lean JSON for the client; debounce filter changes to limit API chatter; encode filter state in URLs for shareable views; version assets + set cache-control headers to prevent stale deployments.

## Process Notes
- `PROGRESS.md` carries the running prompt snapshot, assumptions, recent activity, and next steps so the project can be resumed instantly.
- Repository stays PHP 8.x shared-hosting friendly (Jolt.co.uk); no framework dependencies required.
- Forced refresh handled via `data-build` tokens + localStorage to address Chrome mobile caching issues.
- IMDb/Rotten Tomatoes indicators follow TMDB-score heuristics to satisfy UI requirements without third-party APIs; this is documented in `PROGRESS.md`.
- Recently viewed ribbon is desktop/tablet only to preserve mobile real estate, per requirement.
- Cache layer auto-disables itself if the hosting environment blocks writes to `storage/cache`, so PHP warnings won’t corrupt API JSON responses.
- TMDB client gracefully falls back to PHP streams whenever cURL isn’t available, keeping API responses reliable on constrained hosts.
- Filter and layout selections persist across sessions via `localStorage`, so providers/genres/sort/view preferences remain intact even after the browser closes.

## Deployment Guide
1. Copy `.env.deploy.example` to `.env.deploy` (kept out of git) and fill in your Jolt FTP/SFTP credentials:
   ```
   DEPLOY_HOST=ftp.jolt.co.uk
   DEPLOY_USER=cpanel-username
   DEPLOY_PASS=secret
   DEPLOY_PATH=/public_html/filmfinder
   DEPLOY_SSL=true
   ```
   (Override via environment variables if you prefer.)
2. Ensure PHP CLI has the FTP extension enabled.
3. Run `php scripts/deploy.php` from the project root. The script:
   - Connects over FTPS (or plain FTP if `DEPLOY_SSL=false`);
   - Recursively uploads all project files except git/artifact directories;
   - Creates destination folders automatically and reports each uploaded file with a dot indicator.
4. For an alternative with verbose logging, run `powershell -ExecutionPolicy Bypass -File scripts/deploy.ps1`; it reads the same `.env.deploy` keys, creates every directory, and echoes each file transfer so you can watch the process.
5. Clear the remote `storage/cache` folder if you need fresh TMDB data (cached files are time-bound but can be deleted safely).

## Testing Roadmap
1. Manual regression: confirm remote filtering, infinite scroll, highlights, trailer/TMDB links, URL sharing, and forced refresh across Chrome, Edge, Firefox, Safari, and iOS/Android mobile browsers.
2. Accessibility: keyboard navigation for filters/cards, focus outlines, and contrast verification against WCAG AA.
3. Backend smoke tests: `php -l` on entry points and sample curl calls to `api.php?action=metadata` & `action=discover` to verify hosting environment compatibility.

## Next Actions
1. Execute the testing roadmap above and capture any issues/fixes.
2. Validate deploy script against actual Jolt credentials (once provided) and document any host-specific nuances.
3. Final polish: provider badge colour validation, copy tweaks, and README usage notes post-testing.

_This README stays in sync with each milestone; refer to `PROGRESS.md` for the detailed running log._
