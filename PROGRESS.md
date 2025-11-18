# Film Finder – Project Log

## Prompt Snapshot
- **Objective:** Build *Film Finder*, a mobile-first, dark-themed aggregator of Netflix, Amazon, Disney, and Apple films in the UK using TMDB data via a PHP backend.
- **Data Strategy:** Hybrid on-demand + caching (30–60 min TTL) without bulk provider downloads; backend-only TMDB calls with normalized JSON responses, pagination support, and graceful error handling.
- **Frontend Goals:** Cinematic/futuristic glass UI inspired by Dribbble streaming concepts; desktop sidebar filters, mobile floating Filters button/overlay, rich film cards (providers, runtime, certification, cast, IMDb/RT badges, trailer link) plus highlights, provider summaries, infinite scroll, and empty states.
- **Process:** Maintain `PROGRESS.md` + `README.md` every interaction, keep code modular for PHP 8.x shared hosting, enforce forced refresh/versioning, prepare GitHub-ready repo with deployment automation.

## Decisions & Assumptions (17 Nov 2025)
- Fresh branding + typography pairing (Space Grotesk headings, Sora body) selected for clarity across browsers.
- Provider labels standardized to **Netflix**, **Amazon**, **Disney**, **Apple**; filters treat providers as AND, genres as OR.
- Genre list fetched dynamically from TMDB and cached long-term; highlight carousel respects active genres and hides when empty.
- Filter state synced via query string for shareable URLs; recently-viewed ribbon appears only on non-mobile widths.
- Forced refresh handled by build version keys + cache-control headers to combat stale Chrome mobile caches.
- IMDb and Rotten Tomatoes indicators are heuristics derived from TMDB scores (scaled) since only TMDB APIs are permitted.
- Deployment tooling will require credentials only at execution time; scripts will read from env/secure files (never committed).

## Outstanding Questions / Clarifications
1. None pending at this stage.

## Planned Next Steps
1. Initialize project structure (public assets, PHP bootstrap). **Completed**
2. Establish configuration, caching utilities, and TMDB client wrapper. **Completed**
3. Build backend API endpoints (discover, metadata, highlights) with caching + error handling. **Completed**
4. Develop responsive frontend shell (HTML/CSS/JS) with filter UI, glass layout, and forced-refresh logic. **Completed**
5. Implement client data flow: metadata-driven filters, debounced remote calls, infinite scroll, highlights, provider summaries, recently viewed, and empty/loading states. **Completed**
6. Add deployment tooling & documentation for Jolt hosting plus full regression/accessibility testing + README refresh. **Completed (tooling/docs)** – testing to follow per roadmap.

## Recent Activity
- Captured full requirements + design inspiration, locked typography, provider naming, and highlight behavior.
- Scaffolded config/bootstrap, filesystem cache store, TMDB client service, HTTP/Request helpers, and API endpoint (`api.php`).
- Added FilmService to normalize filters, fetch details (runtime/certification/cast/providers), provide genre-aware highlights, and supply provider summaries.
- Built frontend shell (`index.php`, `assets/css/app.css`) with mobile overlay filters, forced refresh script, and glass aesthetic tokens.
- Implemented full client logic (`assets/js/app.js`): metadata loading, filter mirroring, URL sync, debounced API calls, IntersectionObserver infinite scroll, highlight cards, provider summary counts, desktop-only recently viewed chips, and graceful empty/loading states.
- Added FTPS-ready deployment helper (`scripts/deploy.php` + `.env.deploy.example`) and published instructions in README.
- Added verbose PowerShell deployment helper (`scripts/deploy.ps1`) that mirrors the `.env.deploy` settings and reports every directory/file transfer.
- Hardened CacheStore so it silently disables itself when the host can't create/write the cache directory (prevents PHP warnings from breaking JSON responses on constrained shared hosting).
- Added HTTP stream fallback to the TMDB client so metadata/film responses succeed even when cURL isn't available on shared hosting.
- Overhauled the UI per design feedback: smaller spacing, rectangular chips, live provider counters that also deselect services, multi-genre selection, year-only release filters, grid/list toggle, BBFC icons, mobile-only highlight hiding, visible mobile filter FAB, compact TMDB/trailer buttons, and optimized infinite scrolling with a sentinel element.
- Documented testing/deployment roadmap; next focus is executing regression + accessibility checks.
