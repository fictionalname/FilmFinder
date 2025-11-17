# Film Finder

Modern cinematic web app for UK streaming availability powered by TMDB with a PHP backend and dynamic caching.

## Current Status
- Project kickoff complete: requirements captured, design inspiration selected (glassy streaming UI concepts), architecture planning underway.
- Implementation pending user confirmation on outstanding clarifications (if any).

## Core Objectives
- Aggregate films currently on Netflix, Amazon, Disney, and Apple (UK region) without bulk-downloading provider catalogues.
- Serve a mobile-first, dark, glassy interface with sidebar/floating filter controls, rich film cards, and remote-filtered infinite scroll.
- Provide backend-only TMDB interactions with hybrid on-demand caching for discover queries and metadata.

## Process Notes
- `PROGRESS.md` tracks prompts, assumptions, and next steps; updated after every action.
- Codebase will remain modular, well-documented, and deployment-ready for PHP 8.x shared hosting environments like Jolt.co.uk.

## Next Actions
1. Await user sign-off or extra guidance.
2. Scaffold PHP backend + caching layer.
3. Build frontend shell and data flow.

_This README will evolve as functionality lands; expect detailed setup instructions and feature summaries alongside each milestone._
