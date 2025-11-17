# Film Finder – Project Log

## Prompt Snapshot
- **Objective:** Build “Film Finder,” a mobile-first, dark-themed web app that lists films available on Netflix, Amazon, Disney, and Apple in the UK by querying TMDB through a PHP backend.
- **Key Constraints:** PHP 8.x compatible on shared hosting, no TMDB credentials client-side, hybrid on-demand + caching strategy, infinite scroll, debounced remote filters, AND logic for provider selection, OR logic for genres.
- **Frontend Goals:** Cinematic/futuristic look inspired by glassy streaming app concepts (Dribbble reference #5). Desktop sidebar filters; mobile floating “Filters” button with overlay; rich movie cards with provider badges, ratings, runtime, certification, main cast, summary, trailer link, and TMDB link.
- **Process:** Maintain this log and README after every interaction, keep code modular/documented, ensure ready-to-deploy git repo with frequent commits.

-## Decisions & Assumptions (17 Nov 2025)
- Fresh branding will be created (no prior assets provided).
- Provider labels should read **Netflix**, **Amazon**, **Disney**, **Apple** for compact badges.
- Genres will be fetched dynamically from TMDB and cached for long-lived reuse.
- Visual direction starts with Dribbble “Glassy Streaming App Concepts,” but layout/theme must stay flexible so we can pivot to other inspirations.
- Typography: choosing Space Grotesk for headers and Sora for body copy to match the cinematic/futuristic brief with maximum legibility.
- Certifications: BBFC (GB) data is sufficient; will fall back gracefully if TMDB lacks UK-specific ratings.
- Filter state will be encoded in the URL to allow bookmarking/sharing.
- “Recently viewed” ribbon will be implemented only if it can remain unobtrusive on mobile (likely a horizontally scrollable chip list tucked below the hero on larger screens and hidden/collapsible on phones).
- Accessibility/contrast checks are required before approving any palette changes.
- Deployment automation must include a scriptable path to push updates to hosting, plus documentation.
- Browser cache must be busted reliably (asset versioning + cache-control headers) to force refreshes, addressing prior Chrome mobile issues.
- Highlights must respect currently selected genres; remove the highlights row when no titles match the active genre filters.
- Fresh branding will be created (no prior assets provided).
- Provider labels should read **Netflix**, **Amazon**, **Disney**, **Apple** for compact badges.
- Genres will be fetched dynamically from TMDB and cached for long-lived reuse.
- Visual direction starts with Dribbble “Glassy Streaming App Concepts,” but layout/theme must stay flexible so we can pivot to other inspirations.
- Typography: will select a cinematic, highly legible Google Font pairing optimized for cross-browser compatibility.
- Certifications: BBFC (GB) data is sufficient; will fall back gracefully if TMDB lacks UK-specific ratings.
- Filter state will be encoded in the URL to allow bookmarking/sharing.
- “Recently viewed” ribbon will be implemented only if it can remain unobtrusive on mobile (likely a horizontally scrollable chip list tucked below the hero on larger screens and hidden/collapsible on phones).
- Accessibility/contrast checks are required before approving any palette changes.
- Deployment automation must include a scriptable path to push updates to hosting, plus documentation.
- Browser cache must be busted reliably (asset versioning + cache-control headers) to force refreshes, addressing prior Chrome mobile issues.

## Outstanding Questions / Clarifications
1. Need confirmation on highlight curation approach suggestion (see README & status updates once defined).

## Planned Next Steps
1. Initialize project structure (public/ assets, PHP entry points).
2. Establish configuration + secure TMDB client wrapper with caching layer (filesystem-based, TTL 30–60 min).
3. Build API endpoints for discover queries and metadata (genres/providers).
4. Develop frontend scaffold (HTML + JS) with responsive layout, filters, and data flow.
5. Implement infinite scroll, debounced filters, and UI polish.
6. Iteratively test, document, and push commits.

## Recent Activity
- Logged initial requirements and design direction questions.
- Captured further instructions on typography, certifications, improvements (URL state, legibility, deploy script, cache busting).
- Awaiting confirmation on highlight curation proposal to begin implementation.
