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
- Hidden the top header/status block on small screens so mobile users only see films and the floating Filter button.
- Ensured the floating Filters FAB hides itself while the overlay is open so the film view stays uncluttered and reappears as soon as the sheet is dismissed.
- Removed the `with_origin_country` restriction so the discover results include every UK-streamed film regardless of where it was produced.
- Stopped double-filtering movies by provider and now trust TMDB’s discover response so titles don’t disappear when watch-provider metadata is missing from the detail call.
- Release window now prevents the “To” year from being earlier than the “From” year so filtering stays consistent no matter how the range shifts.
- Mobile search/sort section starts collapsed to keep the overlay compact until the user explicitly expands it.
- Added a floating glass “status pill” that hovers over the content and displays just the provider/film totals while keeping the sidebar lean.
- Genre filters now use OR logic (TMDB `with_genres` pipe-separated) so selecting multiple genres returns any matching film.
- Ensured provider/genre filter buttons register a single click handler so subsequent taps no longer cancel themselves and multi-selection works reliably.
- Documented testing/deployment roadmap; next focus is executing regression + accessibility checks.
- Completely rebuilt the film collection pipeline so each provider is fetched independently for the UK region, aggregated/deduplicated server-side, restricted to flatrate/ad-supported monetization, and annotated with explicit host metadata so film cards, highlights, and provider summaries all reflect the true availability counts.
- Desktop film cards now pin their ratings, availability chips, and trailer action row to the bottom edge so every card footer aligns perfectly across the grid.
- Centered the Watch Trailer CTA text, kept it anchored with the card footers, and added a film-inspired SVG favicon so the browser tab and on-card controls echo the same cinematic branding.
- Wrapped the entire experience in a removable parallax starfield: the attached JPG now drives layered background planes that can be disabled instantly by removing `data-scroll-effects="enabled"` on `<body>`, keeping the effect purely opt-in while the repeat-enabled layers keep covering the viewport as you scroll.
- Dropped the blue “glow” overlay layer so the starfield now renders as a pure black canvas with just the white stars moving in parallax.
- Set the parallax wrapper itself to transparent to remove any residual gradient tint at the top of the viewport.
- Film cards now span their entire grid cell with straight edges and a 20% opaque, blurred backdrop so the starfield is visible throughout each card.
- Added a bottom-center scroll-to-top arrow that fades in once the user scrolls through the main film area and smoothly brings them back to the top when tapped.
- Genre chips now support three states: single-click to include, double-click to exclude (red state), and a tap to clear, with the backend wiring TMDB `without_genres` for excluded IDs so unwanted categories never appear.
- Each card now features an “If you like this film...” row beneath the availability chips, showing two TMDB recommendations limited to the currently selected providers so it’s easy to remove later by stripping that named block.
- Added a persistent hit counter stored under `storage/state` and exposed beneath the Reset/See Films buttons so each “See Films” run increments the all-time total without being wiped by deployments.
