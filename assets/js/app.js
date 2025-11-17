const API_ENDPOINT = 'api.php';
const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';
const RECENT_KEY = 'filmFinderRecentlyViewed';
const DEBOUNCE_DELAY = 350;

const state = {
    metadata: {
        providers: {},
        genres: [],
    },
    filters: {
        providers: [],
        genres: [],
        start_date: '',
        end_date: '',
        sort: 'popularity.desc',
        query: '',
    },
    pagination: {
        page: 1,
        totalPages: 1,
        totalResults: 0,
    },
    movies: [],
    highlights: [],
    loading: false,
    infiniteObserver: null,
    recentlyViewed: [],
};

const elements = {
    providerListDesktop: document.querySelector('[data-role="provider-list"]'),
    providerListMobile: document.querySelector('[data-role="provider-list-mobile"]'),
    genreListDesktop: document.querySelector('[data-role="genre-list"]'),
    genreListMobile: document.querySelector('[data-role="genre-list-mobile"]'),
    providerSummaryLine: document.querySelector('[data-role="provider-summary-line"]'),
    resultsCount: document.querySelector('[data-role="results-count"]'),
    filtersOverlay: document.querySelector('[data-role="filters-overlay"]'),
    floatingButton: document.querySelector('[data-action="open-overlay"]'),
    closeOverlayButton: document.querySelector('[data-action="close-overlay"]'),
    applyOverlayButton: document.querySelector('[data-action="apply-overlay"]'),
    applyFilterButtons: document.querySelectorAll('[data-action="apply-filters"]'),
    resetButtons: document.querySelectorAll('[data-action="reset-filters"]'),
    filmGrid: document.querySelector('[data-role="film-grid"]'),
    emptyState: document.querySelector('[data-role="empty-state"]'),
    loadingIndicator: document.querySelector('[data-role="loading-indicator"]'),
    loadingMessage: document.querySelector('[data-role="loading-message"]'),
    highlightSection: document.querySelector('[data-role="highlights"]'),
    highlightCards: document.querySelector('[data-role="highlight-cards"]'),
    recentSection: document.querySelector('[data-role="recently-viewed"]'),
    recentChips: document.querySelector('[data-role="recently-viewed-chips"]'),
    clearRecentButton: document.querySelector('[data-action="clear-recent"]'),
};

const desktopInputs = {
    start_date: document.querySelector('[data-filter="start_date"]'),
    end_date: document.querySelector('[data-filter="end_date"]'),
    query: document.querySelector('[data-filter="query"]'),
    sort: document.querySelector('[data-filter="sort"]'),
};

const mobileInputs = {
    start_date: document.querySelector('[data-filter-mobile="start_date"]'),
    end_date: document.querySelector('[data-filter-mobile="end_date"]'),
    query: document.querySelector('[data-filter-mobile="query"]'),
    sort: document.querySelector('[data-filter-mobile="sort"]'),
};

const scheduleFiltersUpdate = debounce(() => applyFilters({ resetPage: true }), DEBOUNCE_DELAY);

init();

async function init() {
    parseFiltersFromUrl();
    state.recentlyViewed = loadRecentlyViewed();
    renderRecentlyViewed();

    elements.resultsCount.textContent = 'Loading filters…';
    try {
        await loadMetadata();
        sanitizeFilters();
        renderProviders();
        renderGenres();
        bindFilterInputs();
        bindApplyButtons();
        bindOverlayControls();
        bindRecentControls();
        syncInputMirrors();
        updateProviderSummary();
        await applyFilters({ resetPage: true });
    } catch (error) {
        console.error(error);
        elements.resultsCount.textContent = 'Unable to load filter metadata';
    }

    window.addEventListener('popstate', () => {
        parseFiltersFromUrl();
        sanitizeFilters();
        renderProviders();
        renderGenres();
        syncInputMirrors();
        applyFilters({ resetPage: true, skipUrlUpdate: true });
    });
}

function parseFiltersFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const providers = params.get('providers');
    const genres = params.get('genres');
    state.filters.providers = providers ? providers.split(',').filter(Boolean) : state.filters.providers;
    state.filters.genres = genres ? genres.split(',').filter(Boolean) : state.filters.genres;
    state.filters.start_date = params.get('start_date') || state.filters.start_date;
    state.filters.end_date = params.get('end_date') || state.filters.end_date;
    state.filters.sort = params.get('sort') || state.filters.sort;
    state.filters.query = params.get('query') || state.filters.query;
}

function sanitizeFilters() {
    const providerKeys = Object.keys(state.metadata.providers);
    if (providerKeys.length) {
        state.filters.providers = state.filters.providers.filter((key) => providerKeys.includes(key));
        if (!state.filters.providers.length) {
            state.filters.providers = providerKeys;
        }
    }

    if (state.metadata.genres.length) {
        const allowed = state.metadata.genres.map((genre) => String(genre.id));
        state.filters.genres = state.filters.genres.filter((id) => allowed.includes(String(id)));
    }
}

async function loadMetadata() {
    const data = await fetchJSON('metadata');
    state.metadata.providers = data.providers || {};
    state.metadata.genres = data.genres || [];
    if (!state.filters.providers.length) {
        state.filters.providers = Object.keys(state.metadata.providers);
    }
}

function renderProviders() {
    const { providers } = state.metadata;
    if (!providers || !Object.keys(providers).length) {
        return;
    }
    const selected = state.filters.providers;

    [elements.providerListDesktop, elements.providerListMobile].forEach((container) => {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        Object.entries(providers).forEach(([key, provider]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'provider-pill';
            button.dataset.provider = key;
            button.dataset.active = selected.includes(key).toString();
            button.innerHTML = `
                <span class="provider-pill__dot" style="background:${provider.color};"></span>
                ${provider.label}
            `;
            container.appendChild(button);
        });
    });

    bindProviderEvents();
}

function renderGenres() {
    const { genres } = state.metadata;
    [elements.genreListDesktop, elements.genreListMobile].forEach((container) => {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        genres.forEach((genre) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'genre-chip';
            button.dataset.genre = String(genre.id);
            button.dataset.active = state.filters.genres.includes(String(genre.id)).toString();
            button.textContent = genre.name;
            container.appendChild(button);
        });
    });

    bindGenreEvents();
}

function bindProviderEvents() {
    [elements.providerListDesktop, elements.providerListMobile].forEach((container) => {
        if (!container) return;
        container.addEventListener('click', (event) => {
            const target = event.target.closest('[data-provider]');
            if (!target) return;
            toggleProvider(target.dataset.provider);
        });
    });
}

function bindGenreEvents() {
    [elements.genreListDesktop, elements.genreListMobile].forEach((container) => {
        if (!container) return;
        container.addEventListener('click', (event) => {
            const target = event.target.closest('[data-genre]');
            if (!target) return;
            toggleGenre(target.dataset.genre);
        });
    });
}

function toggleProvider(key) {
    const current = new Set(state.filters.providers);
    if (current.has(key)) {
        current.delete(key);
    } else {
        current.add(key);
    }
    state.filters.providers = current.size ? Array.from(current) : Object.keys(state.metadata.providers);
    renderProviders();
    updateProviderSummary();
    scheduleFiltersUpdate();
}

function toggleGenre(genreId) {
    const current = new Set(state.filters.genres);
    if (current.has(genreId)) {
        current.delete(genreId);
    } else {
        current.add(genreId);
    }
    state.filters.genres = Array.from(current);
    renderGenres();
    scheduleFiltersUpdate();
}

function bindFilterInputs() {
    Object.entries(desktopInputs).forEach(([key, input]) => {
        if (!input) return;
        const handler = () => {
            state.filters[key] = input.value;
            mirrorMobileField(key, input.value);
            scheduleFiltersUpdate();
        };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
    });

    Object.entries(mobileInputs).forEach(([key, input]) => {
        if (!input) return;
        const handler = () => {
            state.filters[key] = input.value;
            mirrorDesktopField(key, input.value);
            scheduleFiltersUpdate();
        };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
    });

    elements.resetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            resetFilters();
            applyFilters({ resetPage: true });
        });
    });
}

function bindApplyButtons() {
    elements.applyFilterButtons.forEach((button) => {
        button.addEventListener('click', () => applyFilters({ resetPage: true }));
    });
    if (elements.applyOverlayButton) {
        elements.applyOverlayButton.addEventListener('click', () => {
            openOverlay(false);
            applyFilters({ resetPage: true });
        });
    }
}

function bindOverlayControls() {
    if (elements.floatingButton) {
        elements.floatingButton.addEventListener('click', () => openOverlay(true));
    }
    if (elements.closeOverlayButton) {
        elements.closeOverlayButton.addEventListener('click', () => openOverlay(false));
    }
    window.addEventListener('resize', handleResizeForOverlay);
}

function bindRecentControls() {
    if (elements.clearRecentButton) {
        elements.clearRecentButton.addEventListener('click', () => {
            state.recentlyViewed = [];
            saveRecentlyViewed();
            renderRecentlyViewed();
        });
    }
    if (elements.recentChips) {
        elements.recentChips.addEventListener('click', (event) => {
            const button = event.target.closest('[data-recent-id]');
            if (!button) return;
            const movie = state.recentlyViewed.find((item) => String(item.id) === button.dataset.recentId);
            if (movie) {
                openExternal(movie.tmdb_url);
            }
        });
    }
}

function resetFilters() {
    state.filters = {
        providers: Object.keys(state.metadata.providers),
        genres: [],
        start_date: '',
        end_date: '',
        sort: 'popularity.desc',
        query: '',
    };
    renderProviders();
    renderGenres();
    syncInputMirrors();
    updateProviderSummary();
    updateUrlFromFilters();
}

function syncInputMirrors() {
    Object.entries(desktopInputs).forEach(([key, input]) => {
        if (input) {
            input.value = state.filters[key] || '';
        }
    });
    Object.entries(mobileInputs).forEach(([key, input]) => {
        if (input) {
            input.value = state.filters[key] || '';
        }
    });
}

function mirrorMobileField(key, value) {
    if (mobileInputs[key]) {
        mobileInputs[key].value = value || '';
    }
}

function mirrorDesktopField(key, value) {
    if (desktopInputs[key]) {
        desktopInputs[key].value = value || '';
    }
}

function updateProviderSummary(summary = null) {
    const line = elements.providerSummaryLine;
    if (!line) return;
    line.innerHTML = '';
    Object.entries(state.metadata.providers).forEach(([key, provider]) => {
        const badge = document.createElement('span');
        badge.className = 'summary-badge';
        const active = state.filters.providers.includes(key);
        const count = summary && summary[key] ? summary[key].count : '—';
        badge.style.borderColor = active ? provider.color : 'var(--ff-color-outline)';
        badge.innerHTML = `
            <span class="badge-dot" style="background:${provider.color};"></span>
            ${provider.label} · <strong>${count}</strong>
        `;
        line.appendChild(badge);
    });
}

function openOverlay(visible) {
    if (!elements.filtersOverlay) return;
    elements.filtersOverlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
    document.body.classList.toggle('overlay-open', visible);
}

function handleResizeForOverlay() {
    if (window.matchMedia('(min-width: 1025px)').matches) {
        openOverlay(false);
    }
    renderRecentlyViewed();
}

async function applyFilters({ resetPage = false, skipUrlUpdate = false } = {}) {
    if (resetPage) {
        state.pagination.page = 1;
        state.movies = [];
        if (elements.filmGrid) {
            elements.filmGrid.innerHTML = '';
        }
    }
    if (!skipUrlUpdate) {
        updateUrlFromFilters();
    }
    await fetchMovies(state.pagination.page);
}

function updateUrlFromFilters() {
    const params = new URLSearchParams();
    if (state.filters.providers.length && state.filters.providers.length !== Object.keys(state.metadata.providers).length) {
        params.set('providers', state.filters.providers.join(','));
    }
    if (state.filters.genres.length) {
        params.set('genres', state.filters.genres.join(','));
    }
    if (state.filters.start_date) {
        params.set('start_date', state.filters.start_date);
    }
    if (state.filters.end_date) {
        params.set('end_date', state.filters.end_date);
    }
    if (state.filters.sort && state.filters.sort !== 'popularity.desc') {
        params.set('sort', state.filters.sort);
    }
    if (state.filters.query) {
        params.set('query', state.filters.query);
    }
    const queryString = params.toString();
    const targetUrl = queryString ? `?${queryString}` : window.location.pathname;
    window.history.replaceState({}, '', targetUrl);
}

async function fetchMovies(page = 1) {
    if (state.loading) {
        return;
    }
    state.loading = true;
    const append = page > 1;
    setLoadingIndicator(true, append ? 'Loading more films…' : 'Fetching cinematic gems…');

    try {
        const params = buildFilterParams();
        params.page = page;
        const data = await fetchJSON('discover', params);
        const movies = data.results || [];

        state.pagination.page = data.pagination?.page || page;
        state.pagination.totalPages = data.pagination?.total_pages || 1;
        state.pagination.totalResults = data.pagination?.total_results || movies.length;

        state.movies = append ? state.movies.concat(movies) : movies;
        renderMovies(movies, { append });
        updateResultsCount();
        updateProviderSummary(data.providers?.summary || null);
        toggleEmptyState();
        setupInfiniteScroll(state.pagination.page < state.pagination.totalPages);

        if (!append) {
            await fetchHighlights();
        }
    } catch (error) {
        console.error(error);
        elements.resultsCount.textContent = 'Error loading films';
    } finally {
        state.loading = false;
        setLoadingIndicator(false);
    }
}

function buildFilterParams() {
    return {
        providers: state.filters.providers,
        genres: state.filters.genres,
        start_date: state.filters.start_date || undefined,
        end_date: state.filters.end_date || undefined,
        sort: state.filters.sort,
        query: state.filters.query || undefined,
    };
}

function renderMovies(movies, { append = false } = {}) {
    if (!elements.filmGrid) return;
    if (!append) {
        elements.filmGrid.innerHTML = '';
    }
    const fragment = document.createDocumentFragment();
    movies.forEach((movie) => {
        fragment.appendChild(createMovieCard(movie));
    });
    elements.filmGrid.appendChild(fragment);
}

function createMovieCard(movie) {
    const card = document.createElement('article');
    card.className = 'film-card';
    card.dataset.movieId = movie.id;
    card.setAttribute('tabindex', '0');

    const poster = document.createElement('div');
    poster.className = 'film-card__poster';
    if (movie.poster_path) {
        const img = document.createElement('img');
        img.src = `${TMDB_IMAGE_BASE}${movie.poster_path}`;
        img.alt = `${movie.title} poster`;
        img.loading = 'lazy';
        poster.appendChild(img);
    } else {
        poster.textContent = getInitials(movie.title);
    }
    card.appendChild(poster);

    if (movie.release_year) {
        const year = document.createElement('span');
        year.className = 'year-pill';
        year.textContent = movie.release_year;
        card.appendChild(year);
    }

    const title = document.createElement('h3');
    title.textContent = movie.title;
    card.appendChild(title);

    const genreLine = document.createElement('p');
    genreLine.className = 'film-card__genres';
    genreLine.textContent = movie.genres?.join(' • ') || 'Genre unknown';
    card.appendChild(genreLine);

    const meta = document.createElement('div');
    meta.className = 'film-card__meta';
    const metaBits = [];
    if (movie.runtime) metaBits.push(`${movie.runtime} min`);
    if (movie.certification) metaBits.push(`BBFC ${movie.certification}`);
    if (movie.cast?.length) metaBits.push(`Cast: ${movie.cast.join(', ')}`);
    meta.textContent = metaBits.join(' • ') || 'Details updating…';
    card.appendChild(meta);

    const summary = document.createElement('p');
    summary.className = 'film-card__summary';
    summary.textContent = movie.overview || 'No synopsis provided.';
    card.appendChild(summary);

    const ratingsRow = document.createElement('div');
    ratingsRow.className = 'film-card__ratings';
    ratingsRow.appendChild(buildRatingBadge('IMDb', movie.ratings?.imdb?.score));
    ratingsRow.appendChild(buildRatingBadge('Rotten Tomatoes', movie.ratings?.rotten_tomatoes?.score, '%'));
    ratingsRow.appendChild(buildRatingBadge('TMDB', movie.ratings?.tmdb?.score));
    card.appendChild(ratingsRow);

    const providersRow = document.createElement('div');
    providersRow.className = 'film-card__providers';
    const providersLabel = document.createElement('span');
    providersLabel.className = 'providers-label';
    providersLabel.textContent = 'Available on:';
    providersRow.appendChild(providersLabel);
    let providerCount = 0;
    Object.values(movie.providers || {}).forEach((provider) => {
        if (!provider.available) return;
        providerCount++;
        providersRow.appendChild(createProviderBadge(provider));
    });
    if (!providerCount) {
        providersLabel.textContent = 'Not on selected services yet';
    }
    card.appendChild(providersRow);

    const actions = document.createElement('div');
    actions.className = 'film-card__actions';
    const tmdbLink = document.createElement('a');
    tmdbLink.className = 'ghost-button film-card__action';
    tmdbLink.href = movie.tmdb_url;
    tmdbLink.target = '_blank';
    tmdbLink.rel = 'noopener noreferrer';
    tmdbLink.textContent = 'View on TMDB';
    tmdbLink.addEventListener('click', () => addRecentlyViewed(movie));

    const trailerLink = document.createElement('a');
    trailerLink.className = 'primary-button film-card__action';
    trailerLink.href = movie.trailer_url;
    trailerLink.target = '_blank';
    trailerLink.rel = 'noopener noreferrer';
    trailerLink.textContent = 'Watch Trailer';
    trailerLink.addEventListener('click', () => addRecentlyViewed(movie));

    actions.append(tmdbLink, trailerLink);
    card.appendChild(actions);

    card.addEventListener('click', (event) => {
        if (event.target.closest('a') || event.target.closest('button')) {
            return;
        }
        addRecentlyViewed(movie);
        openExternal(movie.tmdb_url);
    });

    card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            addRecentlyViewed(movie);
            openExternal(movie.tmdb_url);
        }
    });

    return card;
}

function buildRatingBadge(label, score, suffix = '') {
    const badge = document.createElement('span');
    badge.className = 'rating-badge';
    const value = typeof score === 'number' ? `${score}${suffix}` : '—';
    badge.innerHTML = `<span>${label}</span><strong>${value}</strong>`;
    return badge;
}

function createProviderBadge(provider) {
    const badge = document.createElement('span');
    badge.className = 'provider-badge';
    badge.style.borderColor = provider.color;
    badge.innerHTML = `<span class="badge-dot" style="background:${provider.color};"></span>${provider.label}`;
    return badge;
}

function getInitials(title = '') {
    return title
        .split(' ')
        .map((word) => word.charAt(0))
        .slice(0, 2)
        .join('')
        .toUpperCase() || 'FF';
}

function updateResultsCount() {
    if (!elements.resultsCount) return;
    const { totalResults, page, totalPages } = state.pagination;
    elements.resultsCount.textContent = `${totalResults.toLocaleString()} films · Page ${page} of ${Math.max(totalPages, 1)}`;
}

function toggleEmptyState() {
    if (!elements.emptyState) return;
    const hasResults = state.movies.length > 0;
    elements.emptyState.hidden = hasResults;
    if (!hasResults) {
        elements.filmGrid.innerHTML = '';
    }
}

function setLoadingIndicator(visible, message = 'Fetching cinematic gems…') {
    if (!elements.loadingIndicator) return;
    elements.loadingIndicator.hidden = !visible;
    if (elements.loadingMessage) {
        elements.loadingMessage.textContent = message;
    }
}

function setupInfiniteScroll(hasMore) {
    if (!elements.loadingIndicator) return;
    if (state.infiniteObserver) {
        state.infiniteObserver.disconnect();
        state.infiniteObserver = null;
    }
    if (!hasMore) {
        elements.loadingIndicator.hidden = true;
        return;
    }
    state.infiniteObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && !state.loading && state.pagination.page < state.pagination.totalPages) {
                fetchMovies(state.pagination.page + 1);
            }
        });
    }, { rootMargin: '200px' });

    state.infiniteObserver.observe(elements.loadingIndicator);
}

async function fetchHighlights() {
    if (!elements.highlightSection) return;
    try {
        const data = await fetchJSON('highlights', buildFilterParams());
        state.highlights = data.results || [];
        renderHighlights();
    } catch (error) {
        console.warn('Highlight lookup failed', error);
        elements.highlightSection.hidden = true;
    }
}

function renderHighlights() {
    if (!elements.highlightCards || !elements.highlightSection) return;
    if (!state.highlights.length) {
        elements.highlightSection.hidden = true;
        elements.highlightCards.innerHTML = '';
        return;
    }
    elements.highlightSection.hidden = false;
    elements.highlightCards.innerHTML = '';
    const fragment = document.createDocumentFragment();
    state.highlights.forEach((movie) => {
        const card = document.createElement('article');
        card.className = 'highlight-card';
        card.innerHTML = `
            <h3>${movie.title}</h3>
            <p class="highlight-card__meta">${movie.release_year || '—'} · ${movie.runtime || '?'} min</p>
            <p class="highlight-card__genres">${movie.genres?.join(', ') || 'Genre pending'}</p>
            <p class="highlight-card__summary">${movie.overview || 'Synopsis pending.'}</p>
            <div class="highlight-card__actions">
                <a class="ghost-button" href="${movie.tmdb_url}" target="_blank" rel="noopener noreferrer">View</a>
                <a class="primary-button" href="${movie.trailer_url}" target="_blank" rel="noopener noreferrer">Trailer</a>
            </div>
        `;
        card.addEventListener('click', (event) => {
            if (event.target.closest('a')) return;
            openExternal(movie.tmdb_url);
        });
        fragment.appendChild(card);
    });
    elements.highlightCards.appendChild(fragment);
}

function loadRecentlyViewed() {
    try {
        const stored = localStorage.getItem(RECENT_KEY);
        if (!stored) return [];
        return JSON.parse(stored);
    } catch (error) {
        console.warn('Unable to load recent list', error);
        return [];
    }
}

function saveRecentlyViewed() {
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(state.recentlyViewed));
    } catch (error) {
        console.warn('Unable to persist recent list', error);
    }
}

function addRecentlyViewed(movie) {
    if (!movie || !movie.id) return;
    const existing = state.recentlyViewed.filter((item) => item.id !== movie.id);
    state.recentlyViewed = [{ id: movie.id, title: movie.title, tmdb_url: movie.tmdb_url }, ...existing].slice(0, 8);
    saveRecentlyViewed();
    renderRecentlyViewed();
}

function renderRecentlyViewed() {
    if (!elements.recentSection || !elements.recentChips) return;
    if (!state.recentlyViewed.length || window.matchMedia('(max-width: 768px)').matches) {
        elements.recentSection.hidden = true;
        return;
    }
    elements.recentSection.hidden = false;
    elements.recentChips.innerHTML = '';
    const fragment = document.createDocumentFragment();
    state.recentlyViewed.forEach((item) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'recent-chip';
        chip.dataset.recentId = item.id;
        chip.textContent = item.title;
        fragment.appendChild(chip);
    });
    elements.recentChips.appendChild(fragment);
}

function openExternal(url) {
    if (!url) return;
    window.open(url, '_blank', 'noopener');
}

async function fetchJSON(action, params = {}) {
    const query = new URLSearchParams({ action });
    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return;
        if (Array.isArray(value)) {
            query.append(key, value.join(','));
        } else {
            query.append(key, value);
        }
    });
    const response = await fetch(`${API_ENDPOINT}?${query.toString()}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
    });
    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }
    return response.json();
}

function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(null, args), delay);
    };
}
