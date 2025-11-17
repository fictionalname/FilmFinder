# Film Finder

Modern cinematic web app for UK streaming availability powered by TMDB with a PHP backend, hybrid caching, and a fully remote-filtered client experience.

## Current Status
- Requirements + visual direction captured; typography locked to Space Grotesk (headings) and Sora (body) for consistent futuristic styling.
- Backend foundations complete: bootstrap/config, filesystem cache store, TMDB client wrapper, FilmService (normalisation, runtime/certification/cast enrichment, provider summaries, genre-aware highlights), and a single API entry (`public/api.php`) exposing discover/metadata/highlights endpoints.
- Frontend experience live: PHP-driven `index.php` with forced refresh/version tokens, responsive glass/glow layout, floating Filters FAB/overlay, provider summary line, highlight carousel stub, film grid, and desktop-only recently viewed panel.
- Client logic implemented (`public/assets/js/app.js`): metadata loading, filter mirroring across desktop/mobile, query-string state sync, debounced backend calls, IntersectionObserver infinite scroll, genre-aware highlights, provider count updates, empty/loading states, and localStorage-powered recently viewed chips (hidden on small screens).
- Film cards show runtime, BBFC certification, cast, provider badges, trailer + TMDB links, and IMDb / Rotten Tomatoes badges derived heuristically from TMDB scores (permitted data source).

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

## Next Actions
1. Implement deployment automation/documentation (e.g., secure rsync/SFTP script) for pushing builds to Jolt, noting where credentials should be supplied.
2. Run regression + accessibility sweeps (contrast checks, keyboard flows, Chrome/Firefox/Safari/iOS/Android) once API + frontend wiring stabilizes; capture results in README/testing notes.
3. Final polish: provider badge colour validation, copy tweaks, and final README usage instructions before handoff.

_This README stays in sync with each milestone; refer to `PROGRESS.md` for the detailed running log._
