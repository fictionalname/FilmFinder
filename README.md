# Film Finder

Modern cinematic web app for UK streaming availability powered by TMDB with a PHP backend and dynamic caching.

## Current Status
- Project kickoff complete: requirements captured, design inspiration selected (glassy streaming UI concepts), architecture planning underway.
- Clarifications received on typography (Space Grotesk for headings, Sora for body), certification scope (BBFC), improvements (URL-sharing filters, cache-busting refresh, deploy automation).
- Backend foundations live: config/bootstrap, cache utilities, TMDB client, request helper, FilmService with normalization/enrichment, and `public/api.php` entry surfacing discover, metadata, and highlight endpoints.
- Frontend shell implemented: PHP-driven `index.php` with forced refresh/version tokens, responsive glassy layout, provider summary row, highlight & film grid containers, floating filters FAB, and base JS for metadata loading + filter mirroring (desktop/mobile).
- Highlights obey genre selections and automatically hide when no matches exist.

## Core Objectives
- Aggregate films currently on Netflix, Amazon, Disney, and Apple (UK region) without bulk-downloading provider catalogues.
- Serve a mobile-first, dark, glassy interface with sidebar/floating filter controls, rich film cards, and remote-filtered infinite scroll.
- Provide backend-only TMDB interactions with hybrid on-demand caching for discover queries and metadata.
- Encode filter state in the URL for shareable views and ensure assets are versioned/cache-busted to avoid stale deployments on any browser/device.
- Highlight row will only surface when at least one film matches the current genre filters.

## Process Notes
- `PROGRESS.md` tracks prompts, assumptions, and next steps; updated after every action.
- Codebase will remain modular, well-documented, and deployment-ready for PHP 8.x shared hosting environments like Jolt.co.uk.
- Automated deployment script + documentation will be provided for pushing updates to hosting.
- Accessibility (contrast/legibility) checks will gate visual choices; “recently viewed” section will only ship if it stays unobtrusive on small screens.

## Next Actions
1. Wire data flow: debounced requests, provider counts, highlights, infinite scroll, URL/state syncing, empty states, and testing harness.
2. Implement deployment automation/documentation plus environment credential guidance for Jolt.
3. Finalize accessibility/contrast reviews, forced-refresh verification, and regression testing ahead of release.

_This README will evolve as functionality lands; expect detailed setup instructions and feature summaries alongside each milestone._
