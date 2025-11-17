const API_ENDPOINT = 'api.php';

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
    resetButtons: document.querySelectorAll('[data-action="reset-filters"]'),
    filtersPanel: document.querySelector('[data-role="filters-panel"]'),
    filmGrid: document.querySelector('[data-role="film-grid"]'),
    emptyState: document.querySelector('[data-role="empty-state"]'),
    loadingIndicator: document.querySelector('[data-role="loading-indicator"]'),
    highlightSection: document.querySelector('[data-role="highlights"]'),
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

init();

async function init() {
    elements.resultsCount.textContent = 'Loading filters…';
    try {
        await loadMetadata();
        renderProviders();
        renderGenres();
        bindFilterInputs();
        updateProviderSummary();
        syncInputMirrors();
        elements.resultsCount.textContent = 'Filters ready – adjust and apply to load films';
    } catch (error) {
        console.error(error);
        elements.resultsCount.textContent = 'Unable to load filter metadata';
    }
    bindOverlayControls();
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
            button.style.setProperty('--provider-pill-color', provider.color);
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
            const key = target.dataset.provider;
            toggleProvider(key);
        });
    });
}

function bindGenreEvents() {
    [elements.genreListDesktop, elements.genreListMobile].forEach((container) => {
        if (!container) return;
        container.addEventListener('click', (event) => {
            const target = event.target.closest('[data-genre]');
            if (!target) return;
            const genreId = target.dataset.genre;
            toggleGenre(genreId);
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
}

function bindFilterInputs() {
    Object.entries(desktopInputs).forEach(([key, input]) => {
        if (!input) return;
        const handler = () => {
            state.filters[key] = input.value;
            mirrorMobileField(key, input.value);
        };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
    });

    Object.entries(mobileInputs).forEach(([key, input]) => {
        if (!input) return;
        const handler = () => {
            state.filters[key] = input.value;
            mirrorDesktopField(key, input.value);
        };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
    });

    elements.resetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            resetFilters();
        });
    });
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

function updateProviderSummary() {
    const line = elements.providerSummaryLine;
    if (!line) return;
    line.innerHTML = '';
    Object.entries(state.metadata.providers).forEach(([key, provider]) => {
        const badge = document.createElement('span');
        badge.className = 'summary-badge';
        const active = state.filters.providers.includes(key);
        badge.style.borderColor = active ? provider.color : 'var(--ff-color-outline)';
        badge.innerHTML = `<strong>${provider.label}</strong> · <span data-count>${active ? '—' : '—'}</span>`;
        line.appendChild(badge);
    });
}

function bindOverlayControls() {
    if (elements.floatingButton) {
        elements.floatingButton.addEventListener('click', () => openOverlay(true));
    }
    if (elements.closeOverlayButton) {
        elements.closeOverlayButton.addEventListener('click', () => openOverlay(false));
    }
    if (elements.applyOverlayButton) {
        elements.applyOverlayButton.addEventListener('click', () => {
            openOverlay(false);
        });
    }
    window.addEventListener('resize', handleResizeForOverlay);
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
        headers: {
            'Accept': 'application/json',
        },
        cache: 'no-store',
    });
    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }
    return response.json();
}
