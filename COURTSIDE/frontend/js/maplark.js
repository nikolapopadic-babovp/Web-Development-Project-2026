/* COURTSIDE Milestone 3: live sports-facility discovery (the API key stays in PHP config).
 * Submit events are delegated because the SPA replaces the courts view on every navigation.
 */
(function () {
  'use strict';

  const areas = {
    'sarajevo': [43.8563, 18.4131],
    'east-sarajevo': [43.8222, 18.3694],
    'mostar': [43.3438, 17.8078],
    'banja-luka': [44.7722, 17.1910]
  };
  const apiUrl = new URL('../backend/api/external/sports-halls', document.baseURI);
  let isBound = false;

  function addText(parent, tag, className, content) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = content;
    parent.appendChild(element);
    return element;
  }

  function showHalls(target, halls) {
    target.replaceChildren();
    for (const hall of halls) {
      const wrapper = document.createElement('div');
      wrapper.className = 'col-md-6 col-xl-4';
      const card = document.createElement('article');
      card.className = 'surface-card live-court-card p-3 h-100';
      addText(card, 'h3', 'h6 fw-bold mb-2', hall.name || 'Unnamed sports facility');
      const tags = [hall.category ? `Category: ${hall.category.replaceAll('_', ' ')}` : null,
        hall.sport ? `Sport: ${hall.sport}` : null,
        hall.basketball_evidence === 'confirmed' ? 'Basketball tagged in OSM' : 'Potential indoor basketball venue (not verified)',
        hall.access ? `Access: ${hall.access}` : null].filter(Boolean);
      addText(card, 'p', 'small muted mb-3', tags.join(' · ') || 'Mapped sports facility');
      const latitude = Number(hall.latitude);
      const longitude = Number(hall.longitude);
      if (Number.isFinite(latitude) && Math.abs(latitude) <= 90 &&
          Number.isFinite(longitude) && Math.abs(longitude) <= 180) {
        const link = document.createElement('a');
        link.className = 'link-quiet small';
        // Build our own link from validated coordinates, not arbitrary provider-provided URLs.
        link.href = `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=17/${latitude}/${longitude}`;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'View on OpenStreetMap ↗';
        card.appendChild(link);
      }
      wrapper.appendChild(card);
      target.appendChild(wrapper);
    }
  }

  async function handleSubmit(event) {
    const form = event.target;
    if (!form || form.id !== 'liveCourtForm') return;
    event.preventDefault();

    const results = document.getElementById('liveCourtResults');
    const status = document.getElementById('liveCourtStatus');
    const button = document.getElementById('liveCourtSubmit');
    const citySelect = document.getElementById('liveCourtCity');
    const radiusSelect = document.getElementById('liveCourtRadius');
    const nameInput = document.getElementById('liveCourtName');
    const areaInput = document.getElementById('liveCourtArea');
    if (!results || !status || !button || !citySelect || !radiusSelect) return;

    const coordinates = areas[citySelect.value];
    const radius = Number(radiusSelect.value);
    if (!coordinates || ![1500, 3000, 5000, 8000].includes(radius)) {
      status.textContent = 'Please choose a valid search area and radius.';
      return;
    }

    const url = new URL(apiUrl.href);
    const params = new URLSearchParams({
      lat: String(coordinates[0]),
      lon: String(coordinates[1]),
      radius: String(radius),
      limit: '20'
    });
    const venueName = (nameInput?.value || '').trim();
    if (venueName) params.set('name', venueName);
    const neighbourhood = (areaInput?.value || '').trim();
    if (neighbourhood) params.set('area', neighbourhood);
    url.search = params.toString();
    button.disabled = true;
    status.textContent = 'Searching mapped basketball venues…';
    results.replaceChildren();

    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload?.message || 'Sports venue discovery is temporarily unavailable.');
      }
      if (!payload?.data || !Array.isArray(payload.data.halls)) {
        throw new Error('Sports venue discovery returned an unexpected response.');
      }
      // If another SPA view replaced this form, do not update the detached elements.
      if (!form.isConnected) return;
      showHalls(results, payload.data.halls);
      const count = payload.data.halls.length;
      const source = payload.data.provider ? ` Source: ${payload.data.provider}.` : '';
      const near = payload.data.search_area ? ` near ${payload.data.search_area}` : '';
      status.textContent = count
        ? `${count} possible basketball ${count === 1 ? 'venue' : 'venues'} found${near}. Venue access and basketball facilities must be verified.${source}`
        : `No mapped basketball courts or suitable sports halls found${near}. Try a wider radius or a different name; OpenStreetMap may not contain every facility.${source}`;
    } catch (error) {
      if (form.isConnected) {
        status.textContent = error?.message || 'Unable to search courts right now.';
      }
    } finally {
      if (form.isConnected) button.disabled = false;
    }
  }

  function bind() {
    if (isBound) return;
    // Delegation survives SPA view replacement; safe to call again on every view render.
    document.addEventListener('submit', handleSubmit);
    isBound = true;
  }

  window.CourtsideExternalCourts = { bind };
  bind();
})();
