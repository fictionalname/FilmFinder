# Film Finder

Modern cinematic web app for UK streaming availability powered by TMDB with a PHP backend and dynamic caching.

## Current Status
- Project kickoff complete: requirements captured, design inspiration selected (glassy streaming UI concepts), architecture planning underway.
- Clarifications received on typography (Space Grotesk for headings, Sora for body), certification scope (BBFC), improvements (URL-sharing filters, cache-busting refresh, deploy automation).
- Backend scaffolding underway: global config, bootstrap/autoloader, cache store, TMDB client wrapper, and HTTP helper are in place.
- Highlights will obey the user’s genre selections and disappear when no matching films are available.

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
1. Implement API endpoints (discover, metadata, highlights) using the TMDB client.
2. Build frontend shell and responsive filter layout consuming the backend.
3. Layer on debounced filter logic, infinite scroll, highlights, and deployment automation/documentation.

_This README will evolve as functionality lands; expect detailed setup instructions and feature summaries alongside each milestone._
