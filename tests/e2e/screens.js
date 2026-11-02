// Seeds a couple with realistic content and screenshots the main screens at phone size (light and dark).
// Needs the API on 8088 (APP_ENV=local, MAIL_DRIVER=log) and the web build served on 8081.
// Usage: node tests/e2e/screens.js <out dir>
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const API = process.env.API || 'http://127.0.0.1:8088', APP = process.env.APP || 'http://127.0.0.1:8081', OUT = process.argv[2] || '.';
const MAIL = process.env.MAIL_LOG || path.join(__dirname, '../../backend/storage/logs/mail.log');
const lastCode = (email) => { for (const b of fs.readFileSync(MAIL, 'utf8').split('---\n').reverse()) { if (b.includes(`To: ${email} `)) { const m = b.match(/code is: (\d{6})/); if (m) return m[1]; } } return null; };
const call = async (method, p, body, tok) => {
  const r = await (await fetch(API + p, { method, headers: { 'Content-Type': 'application/json', ...(tok ? { Authorization: `Bearer ${tok}` } : {}) }, body: body ? JSON.stringify(body) : undefined })).json();
  if (!r.ok && process.env.DEBUG) console.log(method, p, JSON.stringify(r.error));
  return r;
};
async function user(email, name) {
  const r = await call('POST', '/auth/register', { email, password: 'Sunrise2026', display_name: name, dob: '1996-03-14', accept_terms: true, platform: 'web' });
  await call('POST', '/auth/verify-email', { code: lastCode(email) }, r.data.token);
  return r.data.token;
}
const iso = (d) => new Date(Date.now() + d * 86400000).toISOString();
const day = (d) => iso(d).slice(0, 10);

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const s = Date.now();
  const A = await user(`shot_a_${s}@example.com`, 'Aarav');
  const B = await user(`shot_b_${s}@example.com`, 'Diya');
  const inv = await call('POST', '/couple/invite', null, A);
  const j = await call('POST', '/couple/join', { code: inv.data.code }, B);
  await call('POST', `/couple/requests/${j.data.request_id}/accept`, null, A);
  await call('PATCH', '/couple', { relationship_start: day(-812), anniversary_date: day(-812), next_meeting_at: iso(9) }, A);
  const msgs = [[B, 'Good morning jaan ☀️'], [A, 'Morning! Slept well?'], [B, 'Dreamt about our Goa trip 🌊'], [A, 'Haha same. 9 more days!'], [B, 'Can’t wait to see you ❤️'], [A, 'Counting every hour 🥰']];
  for (const [t, body] of msgs) await call('POST', '/messages', { client_id: crypto.randomUUID(), body }, t);
  await call('POST', '/moods', { mood: 'loved' }, B);
  await call('POST', '/moods', { mood: 'excited' }, A);
  await call('POST', '/taps', { kind: 'kiss' }, B);
  await call('POST', '/hub/countdowns', { title: 'Goa trip', target_at: iso(9), emoji: '🏝️' }, A);
  await call('POST', '/hub/bucket', { title: 'See the northern lights' }, A);
  await call('POST', '/hub/memories', { title: 'First date at the café', happened_on: day(-812), body: 'Cold coffee and two hours of talking.' }, B);

  const C = await user(`shot_c_${s}@example.com`, 'Kabir');
  const browser = await chromium.launch();
  const shots = [['welcome', null, '/welcome'], ['login', null, '/login'], ['pair', C, '/pair'], ['home', A, '/'], ['chat', A, '/chat'], ['games', A, '/games'], ['together', A, '/distance'], ['us', A, '/us'], ['settings', A, '/settings'], ['premium', A, '/premium'], ['mood', A, '/mood'], ['daily', A, '/daily'], ['memories', A, '/hub/memories'], ['themes', A, '/themes']];
  const errors = [];
  for (const scheme of ['light', 'dark']) {
    for (const [name, tok, url] of shots) {
      const ctx = await browser.newContext({ viewport: { width: 400, height: 860 }, deviceScaleFactor: 2, colorScheme: scheme });
      if (tok) await ctx.addInitScript((t) => localStorage.setItem('cc_token', t), tok);
      const page = await ctx.newPage();
      page.on('pageerror', (e) => errors.push(`${name}: ${e.message}`));
      await page.goto(APP + url);
      await page.waitForTimeout(3500);
      await page.screenshot({ path: `${OUT}/${scheme}-${name}.png` });
      await ctx.close();
    }
  }
  await browser.close();
  console.log(errors.length ? 'PAGE ERRORS:\n' + errors.join('\n') : 'no page errors');
})();
