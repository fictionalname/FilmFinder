# Film Finder – Project Log

## Prompt Snapshot
- **Objective:** Build “Film Finder,” a mobile-first, dark-themed web app that lists films available on Netflix, Amazon, Disney, and Apple in the UK by querying TMDB through a PHP backend.
- **Key Constraints:** PHP 8.x compatible on shared hosting, no TMDB credentials client-side, hybrid on-demand + caching strategy, infinite scroll, debounced remote filters, AND logic for provider selection, OR logic for genres.
- **Frontend Goals:** Cinematic/futuristic look inspired by glassy streaming app concepts (Dribbble reference #5). Desktop sidebar filters; mobile floating “Filters” button with overlay; rich movie cards with provider badges, ratings, runtime, certification, main cast, summary, trailer link, and TMDB link.
- **Process:** Maintain this log and README after every interaction, keep code modular/documented, ensure ready-to-deploy git repo with frequent commits.

## Decisions & Assumptions (17 Nov 2025)
- Fresh branding will be created (no prior assets provided).
- Provider labels should read **Netflix**, **Amazon**, **Disney**, **Apple** for compact badges.
- Genres will be fetched dynamically from TMDB and cached for long-lived reuse.
- Visual direction starts with Dribbble “Glassy Streaming App Concepts,” but layout/theme must stay flexible so we can pivot to other inspirations.

## Outstanding Questions / Clarifications
1. None pending; awaiting confirmation that all gathered details are sufficient to begin implementation or any extra preferences (e.g., typography choices, copy tone).

## Planned Next Steps
1. Initialize project structure (public/ assets, PHP entry points).
2. Establish configuration + secure TMDB client wrapper with caching layer (filesystem-based, TTL 30–60 min).
3. Build API endpoints for discover queries and metadata (genres/providers).
4. Develop frontend scaffold (HTML + JS) with responsive layout, filters, and data flow.
5. Implement infinite scroll, debounced filters, and UI polish.
6. Iteratively test, document, and push commits.

## Recent Activity
- Logged initial requirements and design direction questions.
- Awaiting user confirmation/improvements before proceeding with build.
