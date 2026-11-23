/* Offline integration check for SPA query parameters; no browser/network needed. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let handler;
let lastUrl;
const controls = {
  liveCourtResults: { replaceChildren() {}, appendChild() {} },
  liveCourtStatus: { textContent: '' },
  liveCourtSubmit: { disabled: false },
  liveCourtCity: { value: 'sarajevo' },
  liveCourtRadius: { value: '1500' },
  liveCourtName: { value: '' },
  liveCourtArea: { value: 'Grbavica' },
};
const form = { id: 'liveCourtForm', isConnected: true };
const document = {
  baseURI: 'http://localhost/Courtside/COURTSIDE/frontend/',
  addEventListener: (name, fn) => { if (name === 'submit') handler = fn; },
  getElementById: id => controls[id] || null,
};
const sandbox = {
  URL, URLSearchParams, document, window: {},
  fetch: async url => { lastUrl = url; return { ok: true, json: async () => ({ data: { halls: [], provider: 'Photon', search_area: 'Grbavica' } }) }; },
};
vm.runInNewContext(fs.readFileSync(__dirname + '/../js/maplark.js', 'utf8'), sandbox);
assert.equal(typeof handler, 'function');
(async () => {
  let prevented = false;
  await handler({ target: form, preventDefault() { prevented = true; } });
  assert(prevented, 'form submission prevented');
  let parsed = new URL(lastUrl);
  assert.equal(parsed.searchParams.get('area'), 'Grbavica');
  assert.equal(parsed.searchParams.has('name'), false);
  assert(parsed.pathname.endsWith('/backend/api/external/sports-halls'));
  assert(controls.liveCourtStatus.textContent.includes('Grbavica'));
  assert.equal(controls.liveCourtSubmit.disabled, false);
  controls.liveCourtName.value = 'Goran Čengić';
  await handler({ target: form, preventDefault() {} });
  parsed = new URL(lastUrl);
  assert.equal(parsed.searchParams.get('name'), 'Goran Čengić');
  assert.equal(parsed.searchParams.get('area'), 'Grbavica');
  console.log('PASS: SPA sends area and venue name independently, handles response, and re-enables button.');
})().catch(e => { console.error(e); process.exitCode = 1; });
