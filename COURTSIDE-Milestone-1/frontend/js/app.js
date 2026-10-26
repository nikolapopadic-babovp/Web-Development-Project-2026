/* COURTSIDE Milestone 1: SPA router and browser-only demo interactions.
 * No API credentials or backend calls are used in this milestone.
 */
(function ($) {
  'use strict';

  const STORAGE_KEYS = {
    favorites: 'courtside_m1_favorites',
    runs: 'courtside_m1_runs',
    joinedRuns: 'courtside_m1_joined_runs',
    profile: 'courtside_m1_profile'
  };

  const courts = [
    { id: 'court-1', name: 'Skenderija Outdoor Courts', city: 'Sarajevo', neighbourhood: 'Centar', address: 'Terezija, Sarajevo', surface: 'asphalt', type: 'outdoor', rating: 4.8, reviews: 24, amenities: ['Full court', 'Lights', 'Water nearby'], description: 'A central spot for a quick game and a familiar place to meet local players.', visual: 'urban', distance: '1.2 km', source: 'Sample data' },
    { id: 'court-2', name: 'Dobrinja Neighbourhood Court', city: 'Sarajevo', neighbourhood: 'Dobrinja', address: 'Dobrinja, Sarajevo', surface: 'rubber', type: 'outdoor', rating: 4.5, reviews: 18, amenities: ['Half court', 'Benches', 'Open access'], description: 'A relaxed neighbourhood court for shooting around and casual pickup games.', visual: 'park', distance: '4.6 km', source: 'Sample data' },
    { id: 'court-3', name: 'East Sarajevo Sports Hall', city: 'East Sarajevo', neighbourhood: 'Lukavica', address: 'Lukavica, East Sarajevo', surface: 'wood', type: 'indoor', rating: 4.9, reviews: 31, amenities: ['Indoor', 'Changing rooms', 'Full court'], description: 'An indoor-court concept for organised sessions when the weather turns.', visual: 'indoor', distance: '2.8 km', source: 'Sample data' },
    { id: 'court-4', name: 'Mostar Riverside Court', city: 'Mostar', neighbourhood: 'Centar', address: 'Riverside, Mostar', surface: 'asphalt', type: 'outdoor', rating: 4.4, reviews: 12, amenities: ['Outdoor', 'Open access', 'River walk'], description: 'An outdoor concept court with space to meet friends and play after class.', visual: 'blue', distance: '—', source: 'Sample data' },
    { id: 'court-5', name: 'Banja Luka Park Court', city: 'Banja Luka', neighbourhood: 'Borik', address: 'Borik, Banja Luka', surface: 'asphalt', type: 'outdoor', rating: 4.6, reviews: 21, amenities: ['Outdoor', 'Benches', 'Park'], description: 'A park-side concept court for shooting drills and a low-pressure run.', visual: 'park', distance: '—', source: 'Sample data' },
    { id: 'court-6', name: 'Grbavica Community Gym', city: 'Sarajevo', neighbourhood: 'Grbavica', address: 'Grbavica, Sarajevo', surface: 'wood', type: 'indoor', rating: 4.7, reviews: 16, amenities: ['Indoor', 'Full court', 'Changing rooms'], description: 'An indoor community-gym concept suited to scheduled pickup sessions.', visual: 'indoor', distance: '3.1 km', source: 'Sample data' }
  ];

  const seedRuns = [
    { id: 'run-101', title: 'Friday Night Buckets', courtId: 'court-1', date: dateOffset(1), time: '18:30', capacity: 10, joined: 6, level: 'Intermediate', notes: 'Bring a light and a dark shirt. We will split teams on arrival.', creator: 'Amar H.', visibility: 'open' },
    { id: 'run-102', title: 'Beginner Friendly Shootaround', courtId: 'court-2', date: dateOffset(2), time: '17:00', capacity: 8, joined: 4, level: 'Beginner friendly', notes: 'No pressure, just fundamentals and fun.', creator: 'Lejla M.', visibility: 'open' },
    { id: 'run-103', title: 'Full Court, Full Energy', courtId: 'court-3', date: dateOffset(3), time: '19:00', capacity: 12, joined: 9, level: 'Competitive', notes: 'Full-court games. Please arrive ten minutes early.', creator: 'Nedim K.', visibility: 'open' },
    { id: 'run-104', title: 'Sunday Park Run', courtId: 'court-5', date: dateOffset(5), time: '11:00', capacity: 10, joined: 3, level: 'Intermediate', notes: 'Outdoor run. Check conditions before travelling.', creator: 'Sara P.', visibility: 'open' }
  ];

  const state = {
    route: '',
    favorites: readStorage(STORAGE_KEYS.favorites, []),
    runs: readStorage(STORAGE_KEYS.runs, null) || seedRuns,
    joinedRuns: readStorage(STORAGE_KEYS.joinedRuns, []),
    profile: readStorage(STORAGE_KEYS.profile, null),
    courtLayout: 'grid'
  };

  const routes = {
    '/': 'home', '/home': 'home', '/courts': 'courts', '/court': 'court-detail',
    '/runs': 'runs', '/run': 'run-detail', '/create-run': 'create-run', '/saved': 'saved',
    '/login': 'login', '/register': 'register', '/profile': 'profile', '/admin': 'admin'
  };

  function dateOffset(days) {
    const d = new Date(); d.setHours(12, 0, 0, 0); d.setDate(d.getDate() + days);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }
  function readStorage(key, fallback) {
    try { const raw = localStorage.getItem(key); return raw === null ? fallback : JSON.parse(raw); }
    catch (error) { return fallback; }
  }
  function saveStorage(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); }
    catch (error) { showToast('Your browser could not save this demo change.', 'warning'); }
  }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  }
  function getCourt(id) { return courts.find(court => court.id === id); }
  function getRun(id) { return state.runs.find(run => run.id === id); }
  function formatDate(value, options) {
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return 'Date to be confirmed';
    return new Intl.DateTimeFormat('en-GB', options || { weekday: 'short', day: 'numeric', month: 'short' }).format(date);
  }
  function formatLongDate(value) { return formatDate(value, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); }
  function isFavorite(id) { return state.favorites.includes(id); }
  function isJoined(id) { return state.joinedRuns.includes(id); }
  function currentUserName() { return state.profile && state.profile.name ? state.profile.name : 'CourtSide Player'; }
  function initials(name) { return String(name || 'CS').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0].toUpperCase()).join('') || 'CS'; }
  function parseRoute() {
    const raw = window.location.hash.replace(/^#/, '') || '/home';
    const [path, queryString] = raw.split('?');
    const query = new URLSearchParams(queryString || '');
    return { path: path || '/home', query };
  }

  async function renderRoute() {
    const { path, query } = parseRoute();
    const viewName = routes[path] || 'not-found';
    state.route = path;
    const appView = document.getElementById('appView');
    if (!appView) return;
    appView.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(`views/${viewName}.html`, { headers: { 'X-Requested-With': 'fetch' } });
      if (!response.ok) throw new Error(`Could not load view ${viewName}`);
      appView.innerHTML = await response.text();
    } catch (error) {
      appView.innerHTML = '<section class="container py-5"><div class="empty-state"><h1 class="h3">Could not load this page</h1><p class="muted">Run the project through XAMPP/Apache so the SPA can load its view templates.</p><a class="btn btn-court rounded-pill" href="#/home">Try home</a></div></section>';
      appView.setAttribute('aria-busy', 'false');
      return;
    }
    appView.setAttribute('aria-busy', 'false');
    updateNavigation();
    bindView(viewName, query);
    if (window.bootstrap && window.bootstrap.Collapse) {
      const nav = document.getElementById('mainNav');
      if (nav && nav.classList.contains('show')) window.bootstrap.Collapse.getOrCreateInstance(nav).hide();
    }
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    document.getElementById('main-content')?.focus({ preventScroll: true });
  }

  function updateNavigation() {
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
      const href = link.getAttribute('href');
      const active = (state.route === '/courts' || state.route === '/court') ? href === '#/courts'
        : (state.route === '/runs' || state.route === '/run' || state.route === '/create-run') ? href === '#/runs'
        : state.route === '/saved' ? href === '#/saved' : false;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
    });
    const profileShortcut = document.getElementById('profileShortcut');
    if (profileShortcut) {
      profileShortcut.classList.toggle('d-none', !state.profile);
      profileShortcut.textContent = initials(currentUserName());
    }
    document.querySelectorAll('[data-auth-link="login"]').forEach(el => el.classList.toggle('d-none', !!state.profile));
    document.querySelectorAll('[data-auth-link="register"]').forEach(el => {
      if (state.profile) { el.textContent = 'My profile ↗'; el.setAttribute('href', '#/profile'); }
      else { el.innerHTML = 'Join the run <span aria-hidden="true">↗</span>'; el.setAttribute('href', '#/register'); }
    });
  }

  function bindView(viewName, query) {
    switch (viewName) {
      case 'home': renderHome(); break;
      case 'courts': bindCourtExplorer(); break;
      case 'court-detail': renderCourtDetail(query.get('id')); break;
      case 'runs': bindRuns(); break;
      case 'run-detail': renderRunDetail(query.get('id')); break;
      case 'create-run': bindCreateRun(); break;
      case 'saved': renderSavedCourts(); break;
      case 'login': bindLogin(); break;
      case 'register': bindRegister(); break;
      case 'profile': renderProfile(); break;
      case 'admin': renderAdmin(); break;
      default: break;
    }
  }

  function courtCard(court) {
    const saved = isFavorite(court.id);
    return `<div class="col-md-6 col-xl-4"><article class="surface-card court-card">
      <div class="court-visual ${escapeHtml(court.visual)}"><span class="court-badge">${court.type === 'indoor' ? 'Indoor' : 'Outdoor'}</span><button type="button" class="favorite-btn ${saved ? 'is-saved' : ''}" data-favorite="${escapeHtml(court.id)}" aria-label="${saved ? 'Remove from' : 'Add to'} saved courts" aria-pressed="${saved}">${saved ? '♥' : '♡'}</button><span class="visual-hoop" aria-hidden="true"></span></div>
      <div class="court-card-body"><div class="d-flex justify-content-between align-items-center gap-2 mb-2"><span class="small-label">${escapeHtml(court.city)}</span><span class="small"><span class="rating-star">★</span> ${court.rating.toFixed(1)} <span class="muted">(${court.reviews})</span></span></div><h3 class="h5 fw-bold mb-2"><a class="link-quiet" href="#/court?id=${encodeURIComponent(court.id)}">${escapeHtml(court.name)}</a></h3><p class="muted small mb-3">⌖ ${escapeHtml(court.neighbourhood)} · ${escapeHtml(court.distance)}</p><div class="d-flex flex-wrap gap-2 mb-3">${court.amenities.slice(0, 2).map(item => `<span class="pill">${escapeHtml(item)}</span>`).join('')}</div><div class="d-flex align-items-center justify-content-between gap-2 pt-3 border-top"><span class="small muted">${escapeHtml(court.surface[0].toUpperCase() + court.surface.slice(1))} surface</span><a class="link-quiet small" href="#/court?id=${encodeURIComponent(court.id)}">Court details ↗</a></div></div>
    </article></div>`;
  }

  function renderHome() {
    const grid = document.getElementById('homeCourtGrid');
    if (grid) grid.innerHTML = courts.slice(0, 3).map(courtCard).join('');
  }

  function bindCourtExplorer() {
    const fields = ['courtSearch', 'courtCity', 'courtSurface', 'courtType', 'courtSort', 'savedOnly'];
    fields.forEach(id => document.getElementById(id)?.addEventListener('input', renderCourtResults));
    document.querySelectorAll('[data-layout]').forEach(button => button.addEventListener('click', () => {
      state.courtLayout = button.dataset.layout;
      document.querySelectorAll('[data-layout]').forEach(item => { const active = item === button; item.classList.toggle('active', active); item.setAttribute('aria-pressed', String(active)); });
      renderCourtResults();
    }));
    renderCourtResults();
  }
  function renderCourtResults() {
    const container = document.getElementById('courtResults'); if (!container) return;
    const search = (document.getElementById('courtSearch')?.value || '').trim().toLowerCase();
    const city = document.getElementById('courtCity')?.value || 'all';
    const surface = document.getElementById('courtSurface')?.value || 'all';
    const type = document.getElementById('courtType')?.value || 'all';
    const sort = document.getElementById('courtSort')?.value || 'recommended';
    const savedOnly = !!document.getElementById('savedOnly')?.checked;
    let result = courts.filter(court => {
      const matchesSearch = `${court.name} ${court.city} ${court.neighbourhood} ${court.address}`.toLowerCase().includes(search);
      return matchesSearch && (city === 'all' || court.city === city) && (surface === 'all' || court.surface === surface) && (type === 'all' || court.type === type) && (!savedOnly || isFavorite(court.id));
    });
    if (sort === 'name') result.sort((a, b) => a.name.localeCompare(b.name));
    if (sort === 'rating') result.sort((a, b) => b.rating - a.rating);
    const count = document.getElementById('courtCount'); if (count) count.textContent = `${result.length} ${result.length === 1 ? 'court' : 'courts'} found`;
    container.classList.toggle('list-view', state.courtLayout === 'list');
    container.innerHTML = result.length ? result.map(courtCard).join('') : `<div class="col-12"><div class="empty-state"><div class="empty-icon">⌕</div><h2 class="h4 fw-bold">No courts match those filters.</h2><p class="muted">Try another city or clear the search to see all sample courts.</p><button type="button" class="btn btn-outline-ink rounded-pill" id="clearCourtFilters">Clear filters</button></div></div>`;
    document.getElementById('clearCourtFilters')?.addEventListener('click', () => {
      document.getElementById('courtSearch').value = ''; document.getElementById('courtCity').value = 'all'; document.getElementById('courtSurface').value = 'all'; document.getElementById('courtType').value = 'all'; document.getElementById('courtSort').value = 'recommended'; document.getElementById('savedOnly').checked = false; renderCourtResults();
    });
  }

  function renderCourtDetail(id) {
    const court = getCourt(id);
    const title = document.getElementById('detailTitle'); const address = document.getElementById('detailAddress');
    const content = document.getElementById('courtDetailContent'); if (!content) return;
    if (!court) {
      if (title) title.textContent = 'Court not found';
      if (address) address.textContent = 'This court is not in the current sample dataset.';
      content.innerHTML = '<div class="empty-state"><div class="empty-icon">⌕</div><h2 class="h4 fw-bold">We could not find that court.</h2><p class="muted">Return to the court explorer to choose a valid sample court.</p><a class="btn btn-court rounded-pill" href="#/courts">Browse courts</a></div>';
      return;
    }
    if (title) title.textContent = court.name; if (address) address.textContent = `${court.address} · ${court.city}`;
    const saved = isFavorite(court.id);
    const mapQuery = encodeURIComponent(`${court.name}, ${court.address}`);
    content.innerHTML = `<div class="row g-4"><div class="col-lg-8"><div class="court-visual ${escapeHtml(court.visual)} rounded-4 court-visual-detail"><span class="court-badge">${court.type === 'indoor' ? 'Indoor court' : 'Outdoor court'}</span><span class="visual-hoop" aria-hidden="true"></span></div><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4"><div><div class="d-flex flex-wrap gap-2 mb-2"><span class="pill pill-orange">${escapeHtml(court.surface)} surface</span><span class="pill">${escapeHtml(court.city)}</span><span class="pill">${escapeHtml(court.source)}</span></div><h2 class="h3 fw-bold mb-2">About this court</h2><p class="muted mb-0">${escapeHtml(court.description)}</p></div><button type="button" class="btn ${saved ? 'btn-ink' : 'btn-outline-ink'} rounded-pill" data-favorite="${escapeHtml(court.id)}" aria-pressed="${saved}">${saved ? '♥ Saved' : '♡ Save court'}</button></div><hr class="my-4"><h3 class="h5 fw-bold mb-3">Court amenities</h3><div class="row g-3">${court.amenities.map(item => `<div class="col-sm-6"><div class="surface-card p-3 d-flex gap-2 align-items-center"><span class="text-court">✓</span><span class="fw-semibold">${escapeHtml(item)}</span></div></div>`).join('')}</div></div><div class="col-lg-4"><div class="surface-card p-4 mb-3"><p class="small-label">Community rating</p><div class="d-flex align-items-baseline gap-2"><span class="display-5 fw-bold">${court.rating.toFixed(1)}</span><span class="rating-star">★★★★★</span></div><p class="muted small">Sample rating · ${court.reviews} sample reviews</p><hr><p class="small-label">Location</p><p class="fw-semibold mb-3">${escapeHtml(court.address)}</p><a class="btn btn-ink rounded-pill w-100" href="https://www.openstreetmap.org/search?query=${mapQuery}" target="_blank" rel="noopener noreferrer">Open map directions ↗</a><p class="map-disclaimer mt-3 mb-0">Opens OpenStreetMap search. Location is not live-verified in this prototype.</p></div><div class="surface-card p-4"><h3 class="h5 fw-bold">Ready to play?</h3><p class="muted small">See if a pickup run is planned for this court.</p><a class="btn btn-court rounded-pill w-100" href="#/runs">Browse pickup games ↗</a><a class="btn btn-outline-ink rounded-pill w-100 mt-2" href="#/create-run?courtId=${encodeURIComponent(court.id)}">Plan a run here</a></div></div></div>`;
  }

  function runCard(run) {
    const court = getCourt(run.courtId); const joined = isJoined(run.id); const full = run.joined >= run.capacity;
    return `<div class="col-lg-6"><article class="surface-card run-card"><div class="d-flex gap-3"><div class="run-date-box"><span>${escapeHtml(formatDate(run.date, { month: 'short' }))}</span><strong>${escapeHtml(formatDate(run.date, { day: 'numeric' }))}</strong><span>${escapeHtml(formatDate(run.date, { weekday: 'short' }))}</span></div><div class="flex-grow-1 min-w-0"><div class="d-flex flex-wrap gap-2 mb-2"><span class="pill pill-orange">${escapeHtml(run.level)}</span>${full ? '<span class="pill">Full</span>' : '<span class="pill pill-green">Open spots</span>'}</div><h3 class="h5 fw-bold mb-1"><a class="link-quiet" href="#/run?id=${encodeURIComponent(run.id)}">${escapeHtml(run.title)}</a></h3><p class="muted small mb-0">⌖ ${escapeHtml(court ? court.name : 'Court to be confirmed')}</p></div></div><div class="run-divider"></div><div class="d-flex flex-wrap justify-content-between gap-2 small muted"><span>◷ ${escapeHtml(run.time)}</span><span>♟ ${run.joined}/${run.capacity} players</span><span>Host: ${escapeHtml(run.creator || 'Player')}</span></div><div class="progress progress-thin mt-3" role="progressbar" aria-label="Run player capacity" aria-valuenow="${run.joined}" aria-valuemin="0" aria-valuemax="${run.capacity}"><div class="progress-bar progress-bar-court" data-progress="${Math.min(100, run.joined / Math.max(1, run.capacity) * 100)}"></div></div><div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-outline-ink rounded-pill flex-grow-1" href="#/run?id=${encodeURIComponent(run.id)}">View details</a><button type="button" class="btn ${joined ? 'btn-ink' : 'btn-court'} rounded-pill flex-grow-1" data-join-run="${escapeHtml(run.id)}" ${full && !joined ? 'disabled' : ''}>${joined ? 'Leave run' : full ? 'Run is full' : 'Join run'}</button></div></article></div>`;
  }
  function applyProgressWidths(container) {
    container.querySelectorAll('[data-progress]').forEach(bar => {
      const value = Number(bar.dataset.progress);
      bar.style.width = `${Math.max(0, Math.min(100, Number.isFinite(value) ? value : 0))}%`;
    });
  }

  function bindRuns() {
    document.getElementById('runFilter')?.addEventListener('change', renderRuns);
    document.getElementById('runLevel')?.addEventListener('change', renderRuns);
    renderRuns();
  }
  function renderRuns() {
    const container = document.getElementById('runResults'); if (!container) return;
    const filter = document.getElementById('runFilter')?.value || 'all'; const level = document.getElementById('runLevel')?.value || 'all';
    const result = state.runs.filter(run => (filter === 'all' || (filter === 'open' && run.joined < run.capacity) || (filter === 'joined' && isJoined(run.id))) && (level === 'all' || run.level === level)).sort((a, b) => `${a.date} ${a.time}`.localeCompare(`${b.date} ${b.time}`));
    container.innerHTML = result.length ? result.map(runCard).join('') : `<div class="col-12"><div class="empty-state"><div class="empty-icon">🏀</div><h2 class="h4 fw-bold">No runs match this view.</h2><p class="muted">Try another filter or create a new pickup game.</p><a class="btn btn-court rounded-pill" href="#/create-run">Create a run</a></div></div>`;
    applyProgressWidths(container);
  }
  function renderRunDetail(id) {
    const run = getRun(id);
    const title = document.getElementById('runDetailTitle'); const subtitle = document.getElementById('runDetailSubtitle');
    const content = document.getElementById('runDetailContent'); if (!content) return;
    if (!run) {
      if (title) title.textContent = 'Pickup run not found';
      if (subtitle) subtitle.textContent = 'This run is not in the current sample dataset.';
      content.innerHTML = '<div class="empty-state"><div class="empty-icon">🏀</div><h2 class="h4 fw-bold">We could not find that pickup run.</h2><p class="muted">Return to upcoming games to choose a valid run.</p><a class="btn btn-court rounded-pill" href="#/runs">Browse pickup games</a></div>';
      return;
    }
    const court = getCourt(run.courtId);
    if (title) title.textContent = run.title; if (subtitle) subtitle.textContent = `${formatLongDate(run.date)} · ${run.time}`;
    const joined = isJoined(run.id); const full = run.joined >= run.capacity;
    content.innerHTML = `<div class="row g-4"><div class="col-lg-8"><div class="surface-card p-4 p-lg-5"><div class="d-flex flex-wrap gap-2 mb-3"><span class="pill pill-orange">${escapeHtml(run.level)}</span><span class="pill">${run.joined}/${run.capacity} players</span><span class="pill">${run.visibility === 'open' ? 'Open run' : 'Friends'}</span></div><h2 class="h3 fw-bold">Let’s get a game going.</h2><p class="muted">${escapeHtml(run.notes || 'No extra notes from the host yet.')}</p><hr class="my-4"><div class="row g-3"><div class="col-sm-6"><p class="small-label mb-1">Date and time</p><p class="fw-semibold">${escapeHtml(formatLongDate(run.date))}<br>${escapeHtml(run.time)}</p></div><div class="col-sm-6"><p class="small-label mb-1">Host</p><p class="fw-semibold">${escapeHtml(run.creator || 'CourtSide Player')}</p></div><div class="col-12"><p class="small-label mb-1">Meeting point</p><p class="fw-semibold mb-0">${escapeHtml(court ? court.name : 'Court to be confirmed')}</p><p class="muted small">${escapeHtml(court ? court.address : '')}</p></div></div><a class="btn btn-outline-ink rounded-pill" href="#/court?id=${encodeURIComponent(court ? court.id : courts[0].id)}">View court details ↗</a></div></div><div class="col-lg-4"><div class="surface-card p-4"><p class="small-label">Players</p><div class="d-flex align-items-baseline gap-2"><span class="display-5 fw-bold">${run.joined}</span><span class="muted">of ${run.capacity} spots</span></div><div class="progress progress-medium my-3" role="progressbar" aria-label="Run player capacity" aria-valuenow="${run.joined}" aria-valuemin="0" aria-valuemax="${run.capacity}"><div class="progress-bar progress-bar-court" data-progress="${Math.min(100, run.joined / Math.max(1, run.capacity) * 100)}"></div></div><p class="muted small">${full ? 'This run is full.' : `${run.capacity - run.joined} spot(s) still open.`}</p><button class="btn ${joined ? 'btn-ink' : 'btn-court'} btn-lg rounded-pill w-100" data-join-run="${escapeHtml(run.id)}" ${full && !joined ? 'disabled' : ''}>${joined ? 'Leave this run' : full ? 'Run is full' : 'Join this run'}</button><p class="map-disclaimer mt-3 mb-0">Prototype interaction only. Membership is stored in this browser, not on a server.</p></div></div></div>`;
    applyProgressWidths(content);
  }

  function bindCreateRun() {
    const courtSelect = document.getElementById('runCourt'); if (courtSelect) {
      courtSelect.innerHTML = courts.map(court => `<option value="${escapeHtml(court.id)}">${escapeHtml(court.name)} — ${escapeHtml(court.city)}</option>`).join('');
      const { query } = parseRoute(); if (getCourt(query.get('courtId'))) courtSelect.value = query.get('courtId');
    }
    const dateInput = document.getElementById('runDate'); if (dateInput) dateInput.min = dateOffset(0);
    document.getElementById('createRunForm')?.addEventListener('submit', event => {
      event.preventDefault(); const form = event.currentTarget;
      const title = document.getElementById('runTitle'); const date = document.getElementById('runDate');
      if (date && date.value < dateOffset(0)) date.setCustomValidity('Date must be today or in the future.'); else date?.setCustomValidity('');
      if (!form.checkValidity()) { form.classList.add('was-validated'); form.querySelector(':invalid')?.focus(); return; }
      const formData = new FormData(form); const run = { id: `run-local-${Date.now()}`, title: String(formData.get('title')).trim(), courtId: String(formData.get('courtId')), date: String(formData.get('date')), time: String(formData.get('time')), capacity: Number(formData.get('capacity')), joined: 1, level: String(formData.get('level')), notes: String(formData.get('notes') || '').trim(), creator: currentUserName(), visibility: String(formData.get('visibility')) };
      if (!run.title || !getCourt(run.courtId) || !Number.isFinite(run.capacity)) { showToast('Check the run details and try again.', 'warning'); return; }
      state.runs.unshift(run); state.joinedRuns.push(run.id); saveStorage(STORAGE_KEYS.runs, state.runs); saveStorage(STORAGE_KEYS.joinedRuns, state.joinedRuns); showToast('Pickup run created in this browser.'); window.location.hash = `#/run?id=${encodeURIComponent(run.id)}`;
    });
  }

  function renderSavedCourts() {
    const container = document.getElementById('savedCourtResults'); if (!container) return;
    const result = courts.filter(court => isFavorite(court.id));
    container.innerHTML = result.length ? result.map(courtCard).join('') : `<div class="col-12"><div class="empty-state"><div class="empty-icon">♡</div><h2 class="h4 fw-bold">Your shortlist starts here.</h2><p class="muted">Tap the heart on any court card to keep it close.</p><a class="btn btn-court rounded-pill" href="#/courts">Explore courts</a></div></div>`;
  }

  function bindLogin() {
    document.getElementById('loginForm')?.addEventListener('submit', event => {
      event.preventDefault(); const form = event.currentTarget;
      if (!form.checkValidity()) { form.classList.add('was-validated'); form.querySelector(':invalid')?.focus(); return; }
      const email = document.getElementById('loginEmail').value.trim(); const role = document.getElementById('demoRole').value;
      state.profile = { name: email.split('@')[0].replace(/[._-]+/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase()) || 'CourtSide Player', email, city: 'Sarajevo', role, demo: true };
      saveStorage(STORAGE_KEYS.profile, state.profile); updateNavigation(); showToast(`Demo ${role} view opened. No real authentication occurred.`); window.location.hash = role === 'admin' ? '#/admin' : '#/profile';
    });
  }
  function bindRegister() {
    document.getElementById('registerForm')?.addEventListener('submit', event => {
      event.preventDefault(); const form = event.currentTarget;
      if (!form.checkValidity()) { form.classList.add('was-validated'); form.querySelector(':invalid')?.focus(); return; }
      state.profile = { name: document.getElementById('registerName').value.trim(), email: document.getElementById('registerEmail').value.trim(), city: document.getElementById('registerCity').value, role: 'player', demo: true };
      saveStorage(STORAGE_KEYS.profile, state.profile); updateNavigation(); showToast('Demo profile created in this browser only.'); window.location.hash = '#/profile';
    });
  }
  function renderProfile() {
    const container = document.getElementById('profileContent'); if (!container) return;
    const profile = state.profile || { name: 'Guest player', email: 'No demo profile yet', city: 'Not set', role: 'player', demo: true };
    const joined = state.runs.filter(run => isJoined(run.id)); const saved = courts.filter(court => isFavorite(court.id));
    container.innerHTML = `<div class="row g-4"><div class="col-lg-4"><div class="surface-card p-4"><div class="profile-avatar mb-3">${escapeHtml(initials(profile.name))}</div><h2 class="h4 fw-bold mb-1">${escapeHtml(profile.name)}</h2><p class="muted small">${escapeHtml(profile.email)}</p><span class="pill pill-orange text-capitalize">${escapeHtml(profile.role)} demo role</span><hr><div class="d-flex justify-content-between py-2"><span class="muted">Home city</span><strong>${escapeHtml(profile.city || 'Not set')}</strong></div><div class="d-flex justify-content-between py-2"><span class="muted">Saved courts</span><strong>${saved.length}</strong></div><div class="d-flex justify-content-between py-2"><span class="muted">Joined runs</span><strong>${joined.length}</strong></div><button class="btn btn-outline-ink rounded-pill w-100 mt-3" id="logoutDemo">Exit demo profile</button></div></div><div class="col-lg-8"><div class="demo-alert mb-4">This profile is stored in localStorage for the prototype. Passwords are not stored and this is not real authentication.</div><div class="surface-card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h5 fw-bold mb-0">My upcoming runs</h3><a class="link-quiet small" href="#/runs">Find more ↗</a></div>${joined.length ? joined.map(run => `<div class="d-flex flex-wrap justify-content-between gap-2 border-top py-3"><div><strong>${escapeHtml(run.title)}</strong><div class="small muted">${escapeHtml(formatDate(run.date))} · ${escapeHtml(run.time)}</div></div><a class="link-quiet small" href="#/run?id=${encodeURIComponent(run.id)}">Details ↗</a></div>`).join('') : '<p class="muted mb-0">No joined runs yet. Find a game and get on the court.</p>'}</div><div class="surface-card p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h5 fw-bold mb-0">Saved courts</h3><a class="link-quiet small" href="#/saved">View saved ↗</a></div>${saved.length ? saved.map(court => `<div class="d-flex flex-wrap justify-content-between gap-2 border-top py-3"><div><strong>${escapeHtml(court.name)}</strong><div class="small muted">${escapeHtml(court.city)}</div></div><a class="link-quiet small" href="#/court?id=${encodeURIComponent(court.id)}">Details ↗</a></div>`).join('') : '<p class="muted mb-0">Save a court and it will show up here.</p>'}</div></div></div>`;
    document.getElementById('logoutDemo')?.addEventListener('click', () => { state.profile = null; saveStorage(STORAGE_KEYS.profile, null); updateNavigation(); showToast('Exited the local demo profile.'); window.location.hash = '#/home'; });
  }
  function renderAdmin() {
    const container = document.getElementById('adminContent'); if (!container) return;
    container.innerHTML = `<div class="row g-3 mb-4"><div class="col-6 col-xl-3"><div class="surface-card stat-card"><div class="stat-value">${courts.length}</div><div class="stat-label">Sample courts</div></div></div><div class="col-6 col-xl-3"><div class="surface-card stat-card"><div class="stat-value">${state.runs.length}</div><div class="stat-label">Sample pickup runs</div></div></div><div class="col-6 col-xl-3"><div class="surface-card stat-card"><div class="stat-value">6</div><div class="stat-label">Planned database entities</div></div></div><div class="col-6 col-xl-3"><div class="surface-card stat-card"><div class="stat-value">M1</div><div class="stat-label">Static prototype</div></div></div></div><div class="surface-card p-4"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-3"><div><h2 class="h4 fw-bold mb-1">Court catalogue preview</h2><p class="muted small mb-0">Read-only sample data. CRUD actions will be connected in later milestones.</p></div><span class="pill pill-orange">Admin UI concept</span></div><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Court</th><th>City</th><th>Type</th><th>Rating (demo)</th><th>Status</th></tr></thead><tbody>${courts.map(court => `<tr><td class="fw-semibold">${escapeHtml(court.name)}</td><td>${escapeHtml(court.city)}</td><td>${escapeHtml(court.type)}</td><td>★ ${court.rating.toFixed(1)}</td><td><span class="pill">Sample</span></td></tr>`).join('')}</tbody></table></div></div><div class="surface-card p-4 mt-4"><h2 class="h5 fw-bold">Planned admin capabilities</h2><div class="row g-3 mt-1"><div class="col-md-4"><span class="text-court">✓</span> Manage court records</div><div class="col-md-4"><span class="text-court">✓</span> Moderate reviews</div><div class="col-md-4"><span class="text-court">✓</span> Manage users and runs</div></div><p class="map-disclaimer mt-3 mb-0">Role checks must be enforced by backend middleware in Milestone 4; hiding buttons in a frontend is not authorization.</p></div>`;
  }

  function toggleFavorite(id) {
    if (!getCourt(id)) return;
    if (isFavorite(id)) { state.favorites = state.favorites.filter(favoriteId => favoriteId !== id); showToast('Court removed from saved spots.'); }
    else { state.favorites.push(id); showToast('Court saved to your shortlist.'); }
    saveStorage(STORAGE_KEYS.favorites, state.favorites);
    if (state.route === '/courts') renderCourtResults();
    else if (state.route === '/saved') renderSavedCourts();
    else if (state.route === '/home') renderHome();
    else if (state.route === '/court') { const { query } = parseRoute(); renderCourtDetail(query.get('id')); }
  }
  function toggleJoinRun(id) {
    const run = getRun(id); if (!run) return;
    if (isJoined(id)) { state.joinedRuns = state.joinedRuns.filter(runId => runId !== id); run.joined = Math.max(0, run.joined - 1); showToast('You left the pickup run.'); }
    else if (run.joined >= run.capacity) { showToast('This run is full.', 'warning'); return; }
    else { state.joinedRuns.push(id); run.joined += 1; showToast('You joined the run in this browser.'); }
    saveStorage(STORAGE_KEYS.joinedRuns, state.joinedRuns); saveStorage(STORAGE_KEYS.runs, state.runs);
    if (state.route === '/runs') renderRuns();
    else if (state.route === '/run') { const { query } = parseRoute(); renderRunDetail(query.get('id')); }
    else if (state.route === '/profile') renderProfile();
  }
  function showToast(message, variant) {
    const region = document.getElementById('toastRegion'); if (!region) return;
    const id = `toast-${Date.now()}-${Math.floor(Math.random() * 10000)}`;
    const toast = document.createElement('div'); toast.className = 'toast align-items-center text-bg-light'; toast.id = id; toast.setAttribute('role', 'status');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    if (variant === 'warning') toast.style.borderLeft = '4px solid #d88908';
    region.appendChild(toast);
    if (window.bootstrap && window.bootstrap.Toast) { const instance = new bootstrap.Toast(toast, { delay: 3000 }); instance.show(); toast.addEventListener('hidden.bs.toast', () => toast.remove()); }
    else { toast.classList.add('show'); setTimeout(() => toast.remove(), 3000); }
  }

  // Delegated handlers keep interactions working when SPA templates are replaced.
  document.addEventListener('click', event => {
    const favorite = event.target.closest('[data-favorite]'); if (favorite) { event.preventDefault(); toggleFavorite(favorite.dataset.favorite); return; }
    const join = event.target.closest('[data-join-run]'); if (join) { event.preventDefault(); toggleJoinRun(join.dataset.joinRun); return; }
    if (event.target.closest('#profileShortcut')) window.location.hash = '#/profile';
  });
  window.addEventListener('hashchange', renderRoute);
  $(function () {
    if (!window.location.hash) window.location.hash = '#/home';
    else renderRoute();
  });
})(window.jQuery || function (callback) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
  else callback();
});
