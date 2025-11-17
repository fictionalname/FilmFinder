const API_ENDPOINT = 'api/tmdb.php';
const POSTER_BASE = 'https://image.tmdb.org/t/p/w185';
const CURRENT_YEAR = new Date().getFullYear();
const CHUNK_FETCH_SIZE = 1;
const CHUNK_DELAY_MS = 250;
const PROVIDERS = [
  { id: 8, name: 'Netflix' },
  { id: 9, name: 'Amazon' },
  { id: 337, name: 'Disney' },
  { id: 350, name: 'Apple' },
];
const PROVIDER_STYLES = {
  8: 'netflix',
  9: 'amazon',
  337: 'disney',
  350: 'apple',
};

let filtersResizeTimer;

const state = {
  movies: [],
  filteredMovies: [],
  providerStatus: {},
  totalCached: 0,
  selectedProviders: new Set(PROVIDERS.map((p) => p.id)),
  selectedGenres: new Set(),
  yearFrom: CURRENT_YEAR,
  yearTo: CURRENT_YEAR,
  searchTerm: '',
  sortOrder: 'newest',
};

const FILTER_STORAGE_KEY = 'filmFinderFilters';

loadFilterState();

function loadFilterState() {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') {
    return;
  }
  try {
    const raw = localStorage.getItem(FILTER_STORAGE_KEY);
    if (!raw) {
      return;
    }
    const parsed = JSON.parse(raw);
    if (!parsed) {
      return;
    }
    const providerIds = new Set(PROVIDERS.map((p) => p.id));
    if (Array.isArray(parsed.providers)) {
      const restored = parsed.providers
        .map((id) => Number(id))
        .filter((id) => providerIds.has(id));
      if (restored.length) {
        state.selectedProviders = new Set(restored);
      }
    }
    if (Array.isArray(parsed.genres)) {
      state.selectedGenres = new Set(parsed.genres.map((g) => Number(g)));
    }
    if (parsed.yearFrom) {
      const year = Number(parsed.yearFrom);
      if (!Number.isNaN(year)) {
        state.yearFrom = year;
      }
    }
    if (parsed.yearTo) {
      const year = Number(parsed.yearTo);
      if (!Number.isNaN(year)) {
        state.yearTo = year;
      }
    }
    if (typeof parsed.searchTerm === 'string') {
      state.searchTerm = parsed.searchTerm;
    }
    if (typeof parsed.sortOrder === 'string') {
      state.sortOrder = parsed.sortOrder;
    }
  } catch (error) {
    console.error('Unable to load filters from storage', error);
  }
}

function saveFilterState() {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') {
    return;
  }
  const payload = {
    providers: Array.from(state.selectedProviders),
    genres: Array.from(state.selectedGenres),
    yearFrom: state.yearFrom,
    yearTo: state.yearTo,
    searchTerm: state.searchTerm,
    sortOrder: state.sortOrder,
  };
  localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(payload));
}

document.addEventListener('DOMContentLoaded', () => {
  populateYearSelectors();
  renderProviderControls();
  attachUIListeners();
  updateFiltersSpacing();
  const searchInput = document.getElementById('text-search');
  const sortSelect = document.getElementById('sort-order');
  if (searchInput) {
    searchInput.value = state.searchTerm;
  }
  if (sortSelect) {
    sortSelect.value = state.sortOrder;
  }
  const filtersToggle = document.getElementById('filters-toggle');
  const viewFilmsBtn = document.getElementById('view-films-btn');
  filtersToggle.addEventListener('click', () => showFilters());
  viewFilmsBtn.addEventListener('click', () => hideFilters());
  filtersToggle.setAttribute('aria-expanded', 'false');
  initializeApp();
});

async function initializeApp() {
  showOverlay();
  let encounteredError = false;
  try {
    const status = await fetchStatus();
    updateProviderStatus(status.providers || []);
    updateOverlay(status);
    await runProviderUpdates(status.providers || []);
    const filmsResponse = await fetchFilms();
    state.movies = filmsResponse.movies || [];
    state.totalCached = state.movies.length;
    renderGenreGrid();
    applyFilters();
  } catch (error) {
    encounteredError = true;
    displayOverlayError(error);
  }
  if (!encounteredError) {
    hideOverlay();
  }
}

async function runProviderUpdates(providers) {
  for (const provider of providers) {
    if (!provider.needsRefresh) {
      continue;
    }
    await downloadProviderChunks(provider.id);
  }
}

async function downloadProviderChunks(providerId) {
  while (true) {
    let chunk;
    try {
      chunk = await fetchChunk(providerId, CHUNK_FETCH_SIZE);
    } catch (error) {
      console.error('Chunk download failed', error);
      break;
    }
    if (!chunk || !chunk.provider) {
      break;
    }
    updateProviderStatus([chunk.provider]);
    updateOverlay({ providers: Object.values(state.providerStatus), overall: chunk.overall });
    if (chunk.toast) {
      showToast(`${chunk.toast.providerName}: ${chunk.toast.added} new films cached`);
    }
    if (!chunk.provider.completed) {
      await delay(CHUNK_DELAY_MS);
      continue;
    }
    break;
  }
}

async function fetchStatus() {
  const res = await fetch(`${API_ENDPOINT}?action=status`);
  return parseJsonSafely(res);
}

async function fetchFilms() {
  const res = await fetch(`${API_ENDPOINT}?action=films`);
  return parseJsonSafely(res);
}

async function fetchChunk(providerId, chunkSize = CHUNK_FETCH_SIZE) {
  const res = await fetch(
    `${API_ENDPOINT}?action=chunk&provider=${providerId}&chunkSize=${chunkSize}&ts=${Date.now()}`
  );
  return parseJsonSafely(res);
}

async function parseJsonSafely(res) {
  const text = await res.text();
  if (!res.ok) {
    throw new Error(text || `Request failed (${res.status})`);
  }
  if (!text) {
    throw new Error('Empty response from server');
  }
  try {
    return JSON.parse(text);
  } catch {
    throw new Error('Invalid JSON received from server');
  }
}

function updateProviderStatus(snapshots) {
  snapshots.forEach((snapshot) => {
    state.providerStatus[snapshot.id] = snapshot;
  });
}

function updateOverlay({ providers = [], overall = {} }) {
  const progress = calculateProgress();
  document.getElementById('overlay-progress').style.width = `${progress}%`;
  const cachedNumber = overall.cachedMovies ?? overall.totalCached ?? state.totalCached;
  state.totalCached = cachedNumber;
  document.getElementById('overlay-film-count').textContent = `${cachedNumber} films cached`;
  renderOverlayProviderStatus();
  renderSummaryCard(overall);
}

function calculateProgress() {
  const total = PROVIDERS.length;
  const completed = PROVIDERS.filter((p) => state.providerStatus[p.id]?.completed).length;
  return Math.round((completed / total) * 100);
}

function renderOverlayProviderStatus() {
  const container = document.getElementById('overlay-provider-status');
  container.innerHTML = '';
  PROVIDERS.forEach((provider) => {
    const meta = state.providerStatus[provider.id];
    const statusText = meta
      ? meta.completed
        ? 'Ready'
        : `Downloading (page ${meta.nextPage || 1})`
      : 'Waiting';
    const countText = meta && typeof meta.cached === 'number' ? `${meta.cached} films` : '';
    container.innerHTML += `
      <div class="provider-status-item">
        <span>${provider.name}</span>
        <span>${statusText}${countText ? ' - ' + countText : ''}</span>
      </div>
    `;
  });
}

function renderSummaryCard(overallStats = {}) {
  const summaryParts = PROVIDERS.map((provider) => {
    const meta = state.providerStatus[provider.id];
    const total = meta?.cached ?? 0;
    const showing = state.filteredMovies.filter((movie) =>
      (movie.provider_ids || []).includes(provider.id)
    ).length;
    return `${provider.name}: ${showing}/${total}`;
  });
  document.getElementById('summary-state').textContent = summaryParts.join(' | ') || 'Preparing films...';
  updateFiltersSpacing();
}

function renderProviderControls() {
  const container = document.getElementById('provider-controls');
  container.innerHTML = '';
  PROVIDERS.forEach((provider) => {
    const label = document.createElement('label');
    const styleKey = PROVIDER_STYLES[provider.id] ?? 'default';
    label.className = `provider-chip provider-chip--${styleKey}`;
    const checked = state.selectedProviders.has(provider.id) ? 'checked' : '';
    label.innerHTML = `
      <input type="checkbox" data-provider="${provider.id}" ${checked} />
      <span>${provider.name}</span>
    `;
    container.appendChild(label);
  });
}

function attachUIListeners() {
  document.getElementById('provider-controls').addEventListener('change', (event) => {
    const input = event.target;
    if (input.matches('input[type="checkbox"]')) {
      const providerId = Number(input.dataset.provider);
      if (input.checked) {
        state.selectedProviders.add(providerId);
      } else {
        state.selectedProviders.delete(providerId);
      }
      applyFilters();
      saveFilterState();
    }
  });

  document.getElementById('year-from').addEventListener('change', (event) => {
    const value = Number(event.target.value);
    state.yearFrom = value;
    if (state.yearFrom > state.yearTo) {
      state.yearTo = state.yearFrom;
      document.getElementById('year-to').value = state.yearTo;
    }
    applyFilters();
    saveFilterState();
  });

  document.getElementById('year-to').addEventListener('change', (event) => {
    const value = Number(event.target.value);
    state.yearTo = value;
    if (state.yearTo < state.yearFrom) {
      state.yearFrom = state.yearTo;
      document.getElementById('year-from').value = state.yearFrom;
    }
    applyFilters();
    saveFilterState();
  });

  document.getElementById('text-search').addEventListener('input', (event) => {
    state.searchTerm = event.target.value.trim();
    applyFilters();
    saveFilterState();
  });

  document.getElementById('sort-order').addEventListener('change', (event) => {
    state.sortOrder = event.target.value;
    applyFilters();
    saveFilterState();
  });
}

function populateYearSelectors() {
  const fromSelect = document.getElementById('year-from');
  const toSelect = document.getElementById('year-to');
  fromSelect.innerHTML = '';
  toSelect.innerHTML = '';
  for (let year = CURRENT_YEAR; year >= 2020; year--) {
    const option = `<option value="${year}">${year}</option>`;
    fromSelect.innerHTML += option;
    toSelect.innerHTML += option;
  }
  fromSelect.value = state.yearFrom >= 2020 ? state.yearFrom : CURRENT_YEAR;
  toSelect.value = state.yearTo >= 2020 ? state.yearTo : CURRENT_YEAR;
  state.yearFrom = Number(fromSelect.value);
  state.yearTo = Number(toSelect.value);
}

function renderGenreGrid() {
  const grid = document.getElementById('genre-grid');
  grid.innerHTML = '';
  const genres = extractGenres();
  genres.forEach((genre) => {
    const label = document.createElement('label');
    label.className = 'genre-option';
    const checked = state.selectedGenres.has(genre.id) ? 'checked' : '';
    label.innerHTML = `
      <input type="checkbox" value="${genre.id}" ${checked} />
      <span>${genre.name}</span>
    `;
    grid.appendChild(label);
  });
  grid.onchange = (event) => {
    const input = event.target;
    if (input.matches('input[type="checkbox"]')) {
      const genreId = Number(input.value);
      if (input.checked) {
        state.selectedGenres.add(genreId);
      } else {
        state.selectedGenres.delete(genreId);
      }
      applyFilters();
      saveFilterState();
    }
  };
}

function extractGenres() {
  const map = new Map();
  state.movies.forEach((movie) => {
    (movie.genres || []).forEach((genre) => {
      if (!map.has(genre.id)) {
        map.set(genre.id, genre.name);
      }
    });
  });
  return Array.from(map.entries())
    .map(([id, name]) => ({ id, name }))
    .sort((a, b) => a.name.localeCompare(b.name));
}

function applyFilters() {
  const filtered = state.movies.filter((movie) => {
    if (state.selectedProviders.size > 0) {
      const hasProvider = (movie.provider_ids || []).some((id) => state.selectedProviders.has(id));
      if (!hasProvider) {
        return false;
      }
    }
    if (state.yearFrom) {
      const releaseYear = Number(movie.year);
      if (releaseYear && (releaseYear < state.yearFrom || releaseYear > state.yearTo)) {
        return false;
      }
    }
    if (state.selectedGenres.size > 0) {
      const hasGenre = (movie.genres || []).some((genre) => state.selectedGenres.has(genre.id));
      if (!hasGenre) {
        return false;
      }
    }
    if (state.searchTerm) {
      const haystack = `${movie.title} ${movie.overview} ${(movie.cast || []).join(' ')}`.toLowerCase();
      if (!haystack.includes(state.searchTerm.toLowerCase())) {
        return false;
      }
    }
    return true;
  });
  const sorted = filtered.slice();
  sortMovies(sorted);
  state.filteredMovies = sorted;
  renderFilmGrid();
  renderSummaryCard();
}

function sortMovies(list) {
  switch (state.sortOrder) {
    case 'rating':
      list.sort((a, b) => (b.vote_average || 0) - (a.vote_average || 0));
      break;
    case 'votes':
      list.sort((a, b) => (b.vote_count || 0) - (a.vote_count || 0));
      break;
    case 'newest':
    default:
      list.sort((a, b) => new Date(b.release_date || 0) - new Date(a.release_date || 0));
      break;
  }
}

function renderFilmGrid() {
  const grid = document.getElementById('film-grid');
  if (!state.filteredMovies.length) {
    grid.innerHTML = '<p class="film-meta">No films match the current filters.</p>';
    return;
  }
  grid.innerHTML = state.filteredMovies
    .map((movie) => {
      const poster = movie.poster_path
        ? `<img src="${POSTER_BASE}${movie.poster_path}" loading="lazy" alt="${movie.title} poster" />`
        : '<div class="film-poster-empty"></div>';
      const cast = (movie.cast || []).slice(0, 5).join(', ');
      const genreTags = (movie.genres || [])
        .map((genre) => `<span>${genre.name}</span>`)
        .join('');
      const providerTags = (movie.providers || [])
        .map((provider) => {
          const styleKey = PROVIDER_STYLES[provider.id] ?? 'default';
          return `<span class="provider-pill provider-pill--${styleKey}">${provider.name}</span>`;
        })
        .join('');
      const trailerQuery = encodeURIComponent(`${movie.title} trailer`);
      const yearLabel = movie.year ? `<span class="film-year">${movie.year}</span>` : '';
      return `
        <article class="film-card">
          <div class="film-poster">
            ${poster}
          </div>
          <div class="film-content">
            <div>
              <h3 class="film-title">${movie.title}</h3>
              ${yearLabel}
            </div>
            <p class="film-meta">Rating: <strong>${movie.vote_average?.toFixed(1) ?? '—'}</strong> · Votes: <strong>${movie.vote_count || '0'}</strong></p>
            <p class="film-overview">${movie.overview || 'Summary unavailable.'}</p>
            <p class="film-meta">Cast: ${(cast || 'Cast unavailable')}</p>
            <div class="film-genres">${genreTags}</div>
            <div class="film-providers">${providerTags}</div>
            <div class="film-links">
              <a href="${movie.tmdb_url}" target="_blank" rel="noopener">View on TMDB</a>
              <a href="https://www.youtube.com/results?search_query=${trailerQuery}" target="_blank" rel="noopener">Watch trailer</a>
            </div>
          </div>
        </article>
      `;
    })
    .join('');
}

function showOverlay() {
  document.getElementById('loading-overlay').classList.remove('hidden');
}

function hideOverlay() {
  document.getElementById('loading-overlay').classList.add('hidden');
}

function displayOverlayError(error) {
  document.querySelector('.overlay-card h2').textContent = 'Unable to load data';
  document.querySelector('.overlay-count').textContent = error.message || 'Please try again later.';
  console.error(error);
}

function showToast(message) {
  const toastArea = document.getElementById('toast-area');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  toastArea.appendChild(toast);
  setTimeout(() => {
    toast.remove();
  }, 3700);
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function showFilters() {
  const overlay = document.getElementById('filters-overlay');
  const toggle = document.getElementById('filters-toggle');
  if (!overlay || !toggle) {
    return;
  }
  overlay.classList.remove('hidden');
  overlay.classList.add('visible');
  toggle.setAttribute('aria-expanded', 'true');
  toggle.style.display = 'none';
  updateFiltersSpacing();
}

function hideFilters() {
  const overlay = document.getElementById('filters-overlay');
  const toggle = document.getElementById('filters-toggle');
  if (!overlay || !toggle) {
    return;
  }
  overlay.classList.remove('visible');
  overlay.classList.add('hidden');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.style.display = 'block';
  updateFiltersSpacing();
  const grid = document.getElementById('film-grid');
  if (grid) {
    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function updateFiltersSpacing() {
  const overlay = document.getElementById('filters-overlay');
  const toggle = document.getElementById('filters-toggle');
  if (!overlay || !toggle) {
    return;
  }
  const height = overlay.classList.contains('visible')
    ? overlay.offsetHeight
    : toggle.offsetHeight + 12;
  document.documentElement.style.setProperty('--filters-overlay-height', `${height}px`);
}

window.addEventListener('resize', () => {
  clearTimeout(filtersResizeTimer);
  filtersResizeTimer = setTimeout(updateFiltersSpacing, 150);
});


