// Two browsers, one couple: relay nudges, a real WebRTC video call with fake camera/mic, and the live drawing board.
// Needs the API (8088), the relay (8090, same RELAY_SECRET) and the web build served on 8081. Usage: node tests/e2e/call_two_browsers.js [shots dir]
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const API = process.env.API || 'http://127.0.0.1:8088', APP = process.env.APP || 'http://127.0.0.1:8081', SHOTS = process.argv[2] || '.';
const MAIL = process.env.MAIL_LOG || path.join(__dirname, '../../backend/storage/logs/mail.log');
const lastCode = (email) => { for (const b of fs.readFileSync(MAIL, 'utf8').split('---\n').reverse()) { if (b.includes(`To: ${email} `)) { const m = b.match(/code is: (\d{6})/); if (m) return m[1]; } } return null; };
const call = async (method, p, body, tok) => (await fetch(API + p, { method, headers: { 'Content-Type': 'application/json', ...(tok ? { Authorization: `Bearer ${tok}` } : {}) }, body: body ? JSON.stringify(body) : undefined })).json();
const results = [];
const check = (n, ok) => (results.push(ok), console.log((ok ? 'PASS ' : 'FAIL ') + n));

async function user(email, name) {
  const r = await call('POST', '/auth/register', { email, password: 'Sunrise2026', display_name: name, dob: '1995-01-01', accept_terms: true, platform: 'web' });
  await call('POST', '/auth/verify-email', { code: lastCode(email) }, r.data.token);
  return r.data.token;
}

(async () => {
  const s = Date.now();
  const A = await user(`call_a_${s}@example.com`, 'Arjun');
  const B = await user(`call_b_${s}@example.com`, 'Diya');
  const inv = await call('POST', '/couple/invite', null, A);
  const j = await call('POST', '/couple/join', { code: inv.data.code }, B);
  await call('POST', `/couple/requests/${j.data.request_id}/accept`, null, A);

  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const open = async (tok) => {
    const ctx = await browser.newContext({ viewport: { width: 412, height: 860 }, permissions: ['camera', 'microphone'] });
    await ctx.addInitScript((t) => localStorage.setItem('cc_token', t), tok);
    const page = await ctx.newPage();
    page.errors = [];
    page.on('pageerror', (e) => page.errors.push(e.message));
    await page.goto(APP + '/chat');
    return page;
  };
  const pa = await open(A);
  const pb = await open(B);
  await pa.getByLabel('Video call').waitFor({ timeout: 20000 });
  await pb.getByLabel('Video call').waitFor({ timeout: 20000 });
  await pa.waitForTimeout(1500);
  const health = await (await fetch('http://127.0.0.1:8090/health')).json();
  check('both apps connected to the relay', health.connections >= 2);

  // Instant delivery through the relay (polling alone would take up to 15 s here)
  const t0 = Date.now();
  await call('POST', '/messages', { client_id: crypto.randomUUID(), body: 'relay ping' }, B);
  await pa.getByText('relay ping').waitFor({ timeout: 2500 });
  check(`message arrives instantly via relay (${Date.now() - t0} ms)`, true);

  await pa.getByLabel('Video call').click();
  await pa.getByText('Ringing…').waitFor({ timeout: 10000 });
  await pb.getByText('Incoming video call').waitFor({ timeout: 8000 });
  check('partner gets the incoming call screen', true);
  await pb.screenshot({ path: `${SHOTS}/call-incoming.png` });
  await pb.getByLabel('Accept').click();
  await pa.getByText(/^0:0\d$/).waitFor({ timeout: 20000 });
  await pb.getByText(/^0:0\d$/).waitFor({ timeout: 20000 });
  check('WebRTC connects on both sides', true);
  const flowing = async (page) => page.evaluate(() => [...document.querySelectorAll('video')].some((v) => v.srcObject && v.videoWidth > 0 && !v.muted));
  await pa.waitForTimeout(1500);
  check('remote video is playing for caller', await flowing(pa));
  check('remote video is playing for callee', await flowing(pb));
  await pa.screenshot({ path: `${SHOTS}/call-connected.png` });
  await pa.getByLabel('Mute').click();
  check('mute toggles', await pa.getByLabel('Unmute').isVisible());
  await pb.getByLabel('End').click();
  await pa.getByText('Call ended').waitFor({ timeout: 10000 });
  check('hang up reaches the other side', true);
  const hist = await call('GET', '/calls', null, A);
  check('call recorded with duration', hist.data[0].status === 'ended' && hist.data[0].duration_seconds >= 1);

  // Live drawing
  await pa.waitForTimeout(2000);
  await pa.goto(APP + '/draw');
  await pb.goto(APP + '/draw');
  await pa.getByText(/Your partner is online/).waitFor({ timeout: 15000 });
  const box = await pa.locator('svg').last().boundingBox();
  await pa.mouse.move(box.x + 40, box.y + 40);
  await pa.mouse.down();
  for (let i = 1; i <= 20; i++) await pa.mouse.move(box.x + 40 + i * 10, box.y + 40 + i * 6);
  await pa.mouse.up();
  await pb.waitForFunction(() => [...document.querySelectorAll('path')].some((p) => (p.getAttribute('d') || '').length > 40), null, { timeout: 5000 });
  check('drawing appears live on partner screen', true);
  await pb.screenshot({ path: `${SHOTS}/draw-partner.png` });

  const errs = [...pa.errors, ...pb.errors];
  check('no page errors', errs.length === 0);
  if (errs.length) console.log(errs.join('\n'));
  await browser.close();
  const failed = results.filter((x) => !x).length;
  console.log(`\n${results.length - failed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error('ERROR', e.message); process.exit(2); });
