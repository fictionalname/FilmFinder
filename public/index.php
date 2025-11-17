<?php

declare(strict_types=1);

use App\Support\Config;

require __DIR__ . '/../bootstrap.php';

$version = Config::get('app.version');
$fonts = Config::get('fonts');
$appName = Config::get('app.name', 'Film Finder');
$metaDescription = 'Discover films across Netflix, Amazon, Disney, and Apple in the UK with unified filters, highlights, and streaming availability.';

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="en-GB" data-build="<?= htmlspecialchars($version, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES) ?>">
    <meta name="theme-color" content="#05060a">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Sora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ff-font-heading: <?= $fonts['heading']; ?>;
            --ff-font-body: <?= $fonts['body']; ?>;
        }
    </style>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= urlencode($version) ?>">
    <script>
        (function () {
            try {
                const buildKey = 'filmFinderBuild';
                const currentBuild = document.documentElement.dataset.build || '';
                const storedBuild = localStorage.getItem(buildKey);
                if (storedBuild && storedBuild !== currentBuild) {
                    localStorage.setItem(buildKey, currentBuild);
                    window.location.reload(true);
                } else if (!storedBuild) {
                    localStorage.setItem(buildKey, currentBuild);
                }
            } catch (error) {
                console.warn('Version sync unavailable', error);
            }
        })();
    </script>
</head>
<body class="app-shell">
<noscript>
    <div class="noscript">
        JavaScript is required to use <?= htmlspecialchars($appName) ?>. Please enable it and refresh.
    </div>
</noscript>
<div id="app" class="app" data-app-version="<?= htmlspecialchars($version) ?>">
    <aside class="filters-panel" data-role="filters-panel">
        <div class="filters-panel__header">
            <div>
                <p class="eyebrow">Streaming Availability</p>
                <h1><?= htmlspecialchars($appName) ?></h1>
            </div>
            <div class="provider-summary" data-role="provider-summary">
                <span class="summary-label">Live counts refresh as you filter</span>
                <div class="summary-line" data-role="provider-summary-line">
                    <!-- Provider summary badges injected via JS -->
                </div>
            </div>
        </div>

        <section class="filters-section">
            <h2>Providers</h2>
            <p class="section-hint">Pick your services (AND logic, duplicates removed)</p>
            <div class="provider-select" data-role="provider-list">
                <!-- Provider toggle buttons -->
            </div>
        </section>

        <section class="filters-section">
            <h2>Release Window</h2>
            <div class="date-range">
                <label>
                    <span>From</span>
                    <input type="date" data-filter="start_date">
                </label>
                <label>
                    <span>To</span>
                    <input type="date" data-filter="end_date">
                </label>
            </div>
        </section>

        <section class="filters-section">
            <h2>Search & Sort</h2>
            <label class="field">
                <span>Title / keyword</span>
                <input type="search" placeholder="Search films..." data-filter="query">
            </label>
            <label class="field">
                <span>Sort order</span>
                <select data-filter="sort">
                    <option value="popularity.desc">Popularity ↓</option>
                    <option value="popularity.asc">Popularity ↑</option>
                    <option value="primary_release_date.desc">Newest</option>
                    <option value="primary_release_date.asc">Oldest</option>
                    <option value="vote_average.desc">Best Rated</option>
                    <option value="vote_count.desc">Most Rated</option>
                </select>
            </label>
        </section>

        <section class="filters-section">
            <h2>Genres</h2>
            <p class="section-hint">Choose any genres (OR logic)</p>
            <div class="genre-list" data-role="genre-list">
                <!-- Genre checkboxes injected via JS -->
            </div>
        </section>

        <div class="filters-footer">
            <button class="ghost-button" data-action="reset-filters">Reset</button>
            <button class="primary-button" data-action="apply-filters">See Films</button>
        </div>
    </aside>

    <div class="filters-overlay" data-role="filters-overlay" aria-hidden="true">
        <div class="filters-overlay__sheet">
            <header>
                <h2>Filters</h2>
                <button class="ghost-button" data-action="close-overlay">Close</button>
            </header>
            <div class="filters-overlay__body" data-role="filters-overlay-body">
                <section class="filters-section">
                    <h2>Providers</h2>
                    <p class="section-hint">Pick your services (AND logic)</p>
                    <div class="provider-select" data-role="provider-list-mobile"></div>
                </section>
                <section class="filters-section">
                    <h2>Release Window</h2>
                    <div class="date-range">
                        <label>
                            <span>From</span>
                            <input type="date" data-filter-mobile="start_date">
                        </label>
                        <label>
                            <span>To</span>
                            <input type="date" data-filter-mobile="end_date">
                        </label>
                    </div>
                </section>
                <section class="filters-section">
                    <h2>Search & Sort</h2>
                    <label class="field">
                        <span>Title / keyword</span>
                        <input type="search" placeholder="Search films..." data-filter-mobile="query">
                    </label>
                    <label class="field">
                        <span>Sort order</span>
                        <select data-filter-mobile="sort">
                            <option value="popularity.desc">Popularity ↓</option>
                            <option value="popularity.asc">Popularity ↑</option>
                            <option value="primary_release_date.desc">Newest</option>
                            <option value="primary_release_date.asc">Oldest</option>
                            <option value="vote_average.desc">Best Rated</option>
                            <option value="vote_count.desc">Most Rated</option>
                        </select>
                    </label>
                </section>
                <section class="filters-section">
                    <h2>Genres</h2>
                    <div class="genre-list" data-role="genre-list-mobile"></div>
                </section>
            </div>
            <footer>
                <button class="primary-button" data-action="apply-overlay">See Films</button>
            </footer>
        </div>
    </div>

    <main class="content">
        <header class="content__header">
            <div class="status-pill" data-role="results-count">Loading films…</div>
            <div class="view-controls">
                <button class="ghost-button" data-action="toggle-layout" disabled>Grid</button>
                <button class="ghost-button" data-action="toggle-layout" disabled>List</button>
            </div>
        </header>

        <section class="highlights" data-role="highlights" hidden>
            <div class="section-header">
                <h2>Featured Now</h2>
                <p class="section-hint">Top-matching picks for your filters</p>
            </div>
            <div class="highlight-cards" data-role="highlight-cards">
                <!-- Highlight cards injected via JS -->
            </div>
        </section>

        <section class="recently-viewed" data-role="recently-viewed" hidden>
            <div class="section-header">
                <h3>Recently Viewed</h3>
                <button class="ghost-button ghost-button--small" data-action="clear-recent">Clear</button>
            </div>
            <div class="recently-viewed__chips" data-role="recently-viewed-chips">
                <!-- Chips inserted dynamically -->
            </div>
        </section>

        <section class="film-grid" data-role="film-grid">
            <!-- Movie cards go here -->
        </section>

        <div class="load-state" data-role="loading-indicator">
            <span class="pulse"></span>
            Fetching cinematic gems…
        </div>
        <div class="empty-state" data-role="empty-state" hidden>
            <h3>No films matched those filters</h3>
            <p>Try adjusting the genres or providers to widen the search.</p>
            <button class="ghost-button" data-action="reset-filters">Reset Filters</button>
        </div>
    </main>

    <button class="floating-filter-btn" data-action="open-overlay">
        Filters
    </button>
</div>
<script src="assets/js/app.js?v=<?= urlencode($version) ?>" type="module"></script>
</body>
</html>
