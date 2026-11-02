const { chromium } = require('playwright');
const fs = require('fs');
const API = process.env.API || 'http://127.0.0.1:8088', APP = process.env.APP || 'http://127.0.0.1:8081', SHOTS = process.argv[2] || '.';
const MAIL = process.env.MAIL_LOG || require('path').join(__dirname, '../../backend/storage/logs/mail.log');
const stamp = Date.now();
const emailA = `ui_a_${stamp}@example.com`, emailB = `ui_b_${stamp}@example.com`;
const lastCode = (email) => { const blocks = fs.readFileSync(MAIL, 'utf8').split('---\n').reverse(); for (const b of blocks) { if (b.includes(`To: ${email} `)) { const m = b.match(/code is: (\d{6})/); if (m) return m[1]; } } return null; };
async function call(method, path, body, tok) {
  const r = await fetch(API + path, { method, headers: { 'Content-Type': 'application/json', ...(tok ? { Authorization: `Bearer ${tok}` } : {}) }, body: body ? JSON.stringify(body) : undefined });
  return r.json();
}
const results = []; let PAGE; let ERRS = [];
const check = (name, ok) => { results.push([name, ok]); console.log((ok ? 'PASS ' : 'FAIL ') + name); };

(async () => {
  // Partner B via the API
  const rb = await call('POST', '/auth/register', { email: emailB, password: 'Sunrise2026', display_name: 'Riya', dob: '1996-05-02', accept_terms: true });
  const tokB = rb.data.token;
  await call('POST', '/auth/verify-email', { code: lastCode(emailB) }, tokB);

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 412, height: 915 } }); PAGE = page;
  const errors = ERRS;
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const shot = (n) => page.screenshot({ path: `${SHOTS}/${n}.png` });

  await page.goto(APP + '/');
  await page.getByText('Create account').first().waitFor({ timeout: 20000 });
  check('welcome screen renders', true);
  await shot('01-welcome');
  await page.getByText('Create account').first().click();
  await page.getByText('Create your account').waitFor();
  const inputs = page.locator('input');
  await inputs.nth(0).fill(emailA);
  await inputs.nth(1).fill('Sunrise2026');
  await inputs.nth(2).fill('Aarav');
  await inputs.nth(3).fill('10-03-1995');
  await page.getByText('I am 18 or older').click();
  await shot('02-register');
  await page.getByRole('button', { name: 'Create account' }).click();
  await page.getByText('Check your email').waitFor({ timeout: 15000 });
  check('register -> verify', true);
  await page.locator('input').first().fill(lastCode(emailA));
  await page.getByRole('button', { name: 'Verify' }).click();
  await page.getByText('Connect with your partner').waitFor({ timeout: 15000 });
  check('verify -> pair', true);
  await page.getByRole('button', { name: 'Get my code' }).click();
  await page.getByText(/Expires in/).waitFor({ timeout: 10000 });
  await shot('03-pair-code');
  const code = (await page.locator('text=/^\\d{6}$/').first().innerText()).trim();
  check('invite code shown', /^\d{6}$/.test(code));
  const j = await call('POST', '/couple/join', { code }, tokB);
  check('partner joins with code', j.ok === true);
  await page.getByText('Someone used your code').waitFor({ timeout: 15000 });
  await shot('04-pair-request');
  await page.getByRole('button', { name: 'Accept' }).click();
  await page.getByText(/days/).first().waitFor({ timeout: 15000 });
  await page.getByText('Riya').first().waitFor();
  check('accept -> home with partner', true);
  await page.waitForTimeout(800);
  await shot('05-home');

  // Love tap
  await page.getByLabel('Send Hug').click();
  await page.waitForTimeout(800);
  const taps = await call('GET', '/taps', null, tokB);
  check('love tap reaches partner', JSON.stringify(taps).includes('hug'));

  // Chat
  await page.getByRole('tab', { name: /Chat/ }).click();
  await page.getByText('Say hi').waitFor({ timeout: 10000 });
  await page.getByPlaceholder('Message').fill('Hello from the app 💖');
  await page.getByLabel('Send', { exact: true }).click();
  await page.getByText('Hello from the app 💖').waitFor();
  await page.waitForTimeout(1500);
  const msgsB = await call('GET', '/messages', null, tokB);
  check('message stored on server', msgsB.data.messages.some((m) => m.body === 'Hello from the app 💖'));
  await call('POST', '/messages', { client_id: crypto.randomUUID(), body: 'Hi back from Riya' }, tokB);
  await page.getByText('Hi back from Riya').waitFor({ timeout: 10000 });
  check('partner message arrives via sync', true);
  await shot('06-chat');
  // Long-press menu: react
  await page.getByText('Hi back from Riya').click({ delay: 600 });
  await page.getByText('Pin for both').waitFor({ timeout: 5000 });
  await shot('07-message-menu');
  await page.getByText('❤️').first().click();
  await page.waitForTimeout(1200);
  const after = await call('GET', '/messages', null, tokB);
  check('reaction saved', after.data.messages.some((m) => m.reactions.length > 0));

  // Games
  await page.getByRole('tab', { name: /Games/ }).click();
  await page.getByText('This or That').waitFor({ timeout: 10000 });
  await shot('08-games');
  await page.getByText('This or That').click();
  await page.getByRole('button', { name: 'Invite partner to play' }).click();
  await page.getByText(/Waiting for Riya to join/).waitFor({ timeout: 10000 });
  const sess = await call('GET', '/games/sessions', null, tokB);
  const sid = sess.data[0].id;
  await call('POST', `/games/sessions/${sid}/join`, null, tokB);
  await page.getByText(/Round 1 of/).waitFor({ timeout: 10000 });
  const st = await call('GET', `/games/sessions/${sid}`, null, tokB);
  const opt = st.data.round.question.options[0];
  await call('POST', `/games/sessions/${sid}/answer`, { answer: { choice: opt } }, tokB);
  await page.getByText(opt, { exact: true }).click();
  await page.getByText(/Same answer|Different answers/).waitFor({ timeout: 10000 });
  check('game round plays and reveals', true);
  await shot('09-game-round');
  await page.getByRole('button', { name: 'Stop' }).click();
  await page.getByText('Game stopped').waitFor({ timeout: 10000 });

  // Daily question
  await page.goto(APP + '/daily');
  await page.getByText('Daily question').first().waitFor({ timeout: 10000 });
  await page.locator('textarea, input').first().fill('Our first trip to Goa');
  await page.getByRole('button', { name: 'Answer' }).click();
  await page.getByText('Hasn’t answered yet.').waitFor({ timeout: 10000 });
  check('daily answer saved', true);

  // Hub list
  await page.goto(APP + '/hub/bucket');
  await page.getByRole('button', { name: 'Add' }).click();
  await page.locator('input').first().fill('See the northern lights');
  await page.getByRole('button', { name: 'Save' }).click();
  await page.getByText('See the northern lights').waitFor({ timeout: 10000 });
  check('bucket item added', true);
  await shot('10-bucket');

  // Letters
  await page.goto(APP + '/notes');
  await page.getByRole('button', { name: 'Write' }).click();
  await page.locator('textarea').first().fill('Open this when you miss me.');
  await page.getByRole('button', { name: 'Save' }).click();
  await page.getByText(/Open this when you miss me/).waitFor({ timeout: 10000 });
  check('letter saved', true);

  // Themes, premium, settings, us
  await page.goto(APP + '/themes');
  await page.getByText('Appearance').waitFor({ timeout: 10000 });
  await page.getByText('Dark', { exact: true }).first().click();
  await page.waitForTimeout(800);
  const prefs = await call('GET', '/me/preferences', null, (await page.evaluate(() => localStorage.getItem('cc_token'))));
  check('dark mode saved', prefs.data.color_mode === 'dark');
  await shot('11-themes-dark');
  await page.goto(APP + '/premium');
  await page.getByText(/one-time/).waitFor({ timeout: 10000 });
  await shot('12-premium');
  check('premium page shows price', await page.getByText('₹199').first().isVisible());
  await page.goto(APP + '/settings');
  await page.getByText('Signed-in devices').waitFor({ timeout: 10000 });
  await shot('13-settings');
  check('settings render', true);
  await page.goto(APP + '/us');
  await page.getByText('Your partner').waitFor({ timeout: 10000 });
  await shot('14-us');
  check('us tab renders', true);

  const real = errors.filter((e) => !/favicon|DevTools|Download the React/i.test(e));
  check('no page errors', real.length === 0);
  if (real.length) console.log(real.slice(0, 10).join('\n'));
  await browser.close();
  const failed = results.filter((r) => !r[1]).length;
  console.log(`\n${results.length - failed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch(async (e) => { console.error("ERROR", e.message); console.log(ERRS.slice(0,10).join("\n")); if (PAGE) await PAGE.screenshot({ path: process.argv[2] + "/zz-fail.png" }); process.exit(2); });
