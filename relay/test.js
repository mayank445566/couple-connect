'use strict';
// Self-contained relay test: node test.js
process.env.RELAY_SECRET = 'test_secret_for_relay_1234';
process.env.PORT = '18090';
const crypto = require('crypto');
const http = require('http');
const WebSocket = require('ws');
const { server } = require('./server');

const S = process.env.RELAY_SECRET;
const hmac = (s) => crypto.createHmac('sha256', S).update(s).digest('hex');
const ticket = (uid, cid, exp = Math.floor(Date.now() / 1000) + 300) => {
  const p = `${uid}.${cid}.${exp}.${crypto.randomBytes(6).toString('hex')}`;
  return `${p}.${hmac(p)}`;
};
let pass = 0, fail = 0;
const check = (n, ok) => (ok ? pass++ : fail++, console.log(`${ok ? '  ok  ' : '  FAIL'} ${n}`));
const open = (t) => new Promise((res) => {
  const ws = new WebSocket(`ws://127.0.0.1:18090/ws?ticket=${t}`);
  ws.msgs = [];
  ws.on('message', (m) => ws.msgs.push(JSON.parse(m)));
  ws.on('open', () => res(ws));
  ws.on('error', () => res(null));
  ws.on('unexpected-response', () => res(null));
});
const publish = (obj, sig) => new Promise((res) => {
  const body = JSON.stringify(obj);
  const req = http.request({ host: '127.0.0.1', port: 18090, path: '/publish', method: 'POST', headers: { 'X-Signature': sig ?? hmac(body), 'Content-Type': 'application/json' } }, (r) => {
    let d = '';
    r.on('data', (c) => (d += c));
    r.on('end', () => res({ status: r.statusCode, body: JSON.parse(d) }));
  });
  req.end(body);
});
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  await wait(100);
  check('bad ticket rejected', (await open('1.2.3.abc.deadbeef')) === null);
  check('expired ticket rejected', (await open(ticket(1, 7, 1000))) === null);
  const a = await open(ticket(1, 7));
  const b = await open(ticket(2, 7));
  const stranger = await open(ticket(3, 8));
  check('partners connect', !!a && !!b);
  await wait(100);
  check('partner sees presence', a.msgs.some((m) => m.t === 'presence' && m.online));
  const r = await publish({ couple_id: 7, event_id: 55, type: 'message.created', ts: Math.floor(Date.now() / 1000) });
  await wait(100);
  check('publish delivered to both', r.body.delivered === 2 && b.msgs.some((m) => m.t === 'nudge' && m.event_id === 55));
  check('other couple gets nothing', !stranger.msgs.some((m) => m.t === 'nudge'));
  check('unsigned publish refused', (await publish({ couple_id: 7, ts: Math.floor(Date.now() / 1000) }, 'x'.repeat(64))).status === 401);
  check('stale publish refused', (await publish({ couple_id: 7, ts: 1 })).status === 400);
  a.send(JSON.stringify({ t: 'peer', d: { kind: 'stroke', pts: [1, 2, 3] } }));
  await wait(100);
  check('peer message reaches partner only', b.msgs.some((m) => m.t === 'peer' && m.d.kind === 'stroke') && !a.msgs.some((m) => m.t === 'peer') && !stranger.msgs.some((m) => m.t === 'peer'));
  for (let i = 0; i < 120; i++) a.send(JSON.stringify({ t: 'peer', d: { i } }));
  await wait(200);
  check('peer messages rate limited', b.msgs.filter((m) => m.t === 'peer').length <= 52);
  const closed = new Promise((res) => b.on('close', (code) => res(code)));
  await publish({ couple_id: 7, event_id: 60, type: 'couple.unpaired', ts: Math.floor(Date.now() / 1000) });
  check('unpair closes the room', (await closed) === 4001);
  a.close(); stranger.close();
  console.log(`\n${pass} passed, ${fail} failed`);
  server.close();
  process.exit(fail ? 1 : 0);
})();
