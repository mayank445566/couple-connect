'use strict';
/**
 * Couple Connect relay.
 *
 * - The PHP API POSTs signed nudges to /publish ("something changed for couple 12").
 *   The relay forwards them to that couple's open sockets, which then call GET /sync.
 *   Nudges carry event types and ids only, never message content.
 * - The app connects to /ws?ticket=... with a 5-minute ticket the API signed with the
 *   shared secret. The relay has no database and keeps nothing on disk.
 * - Partners can exchange small live messages ("peer"), used for the drawing board.
 */
const http = require('http');
const crypto = require('crypto');
const { WebSocketServer } = require('ws');

function loadEnv(file) {
  try {
    for (const line of require('fs').readFileSync(file, 'utf8').split('\n')) {
      const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
      if (m && process.env[m[1]] === undefined) process.env[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
  } catch {}
}
loadEnv(require('path').join(__dirname, '.env'));

const PORT = Number(process.env.PORT || 8090);
const HOST = process.env.HOST || '127.0.0.1';
const SECRET = process.env.RELAY_SECRET || '';
const MAX_SOCKETS_PER_USER = 5;
const MAX_PEER_BYTES = 8 * 1024;
if (SECRET.length < 16) {
  console.error('RELAY_SECRET must be set (16+ characters) and match the API .env');
  process.exit(1);
}

const hmac = (s) => crypto.createHmac('sha256', SECRET).update(s).digest('hex');
const safeEqual = (a, b) => a.length === b.length && crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));

/** ticket = "<user>.<couple>.<exp>.<nonce>.<hmac>" */
function verifyTicket(ticket) {
  if (typeof ticket !== 'string' || ticket.length > 200) return null;
  const parts = ticket.split('.');
  if (parts.length !== 5) return null;
  const [uid, cid, exp, nonce, sig] = parts;
  if (!/^\d+$/.test(uid) || !/^\d+$/.test(cid) || !/^\d+$/.test(exp) || !/^[a-f0-9]+$/.test(nonce)) return null;
  if (!safeEqual(hmac(`${uid}.${cid}.${exp}.${nonce}`), sig)) return null;
  if (Number(exp) < Date.now() / 1000) return null;
  return { userId: Number(uid), coupleId: Number(cid) };
}

/** coupleId -> Set<ws> */
const rooms = new Map();
const stats = { connections: 0, published: 0, peer: 0 };

function send(ws, obj) {
  if (ws.readyState === ws.OPEN) ws.send(JSON.stringify(obj));
}
function roomOf(cid) {
  if (!rooms.has(cid)) rooms.set(cid, new Set());
  return rooms.get(cid);
}
function partnerOnline(room, userId) {
  for (const s of room) if (s.userId !== userId) return true;
  return false;
}
function closeRoom(cid, reason) {
  const room = rooms.get(cid);
  if (!room) return;
  for (const s of room) s.close(4001, reason);
  rooms.delete(cid);
}

function readBody(req, limit = 16 * 1024) {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks = [];
    req.on('data', (c) => {
      size += c.length;
      if (size > limit) {
        reject(new Error('too large'));
        req.destroy();
      } else chunks.push(c);
    });
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    req.on('error', reject);
  });
}

const server = http.createServer(async (req, res) => {
  const reply = (code, obj) => {
    res.writeHead(code, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(obj));
  };
  if (req.method === 'GET' && req.url === '/health') {
    return reply(200, { ok: true, rooms: rooms.size, connections: stats.connections });
  }
  if (req.method === 'POST' && req.url === '/publish') {
    let body;
    try {
      body = await readBody(req);
    } catch {
      return reply(413, { ok: false });
    }
    const sig = String(req.headers['x-signature'] || '');
    if (!safeEqual(hmac(body), sig)) return reply(401, { ok: false, error: 'bad signature' });
    let msg;
    try {
      msg = JSON.parse(body);
    } catch {
      return reply(400, { ok: false });
    }
    if (!Number.isInteger(msg.couple_id) || Math.abs(Date.now() / 1000 - Number(msg.ts)) > 120) return reply(400, { ok: false, error: 'stale' });
    stats.published++;
    const room = rooms.get(msg.couple_id);
    let delivered = 0;
    if (room) {
      const out = { t: 'nudge', type: String(msg.type || ''), event_id: Number(msg.event_id) || 0 };
      if (msg.call_id) out.call_id = Number(msg.call_id);
      for (const s of room) {
        send(s, out);
        delivered++;
      }
    }
    // A couple that ended loses its live connection immediately
    if (msg.type === 'couple.unpaired') closeRoom(msg.couple_id, 'unpaired');
    return reply(200, { ok: true, delivered });
  }
  reply(404, { ok: false });
});

const wss = new WebSocketServer({ noServer: true, maxPayload: MAX_PEER_BYTES + 512 });

server.on('upgrade', (req, socket, head) => {
  const url = new URL(req.url, 'http://x');
  if (url.pathname !== '/ws') return socket.destroy();
  const who = verifyTicket(url.searchParams.get('ticket'));
  if (!who) {
    socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
    return socket.destroy();
  }
  wss.handleUpgrade(req, socket, head, (ws) => {
    ws.userId = who.userId;
    ws.coupleId = who.coupleId;
    wss.emit('connection', ws);
  });
});

wss.on('connection', (ws) => {
  const room = roomOf(ws.coupleId);
  // Limit sockets per person (several devices are fine; a reconnect loop is not)
  const mine = [...room].filter((s) => s.userId === ws.userId);
  if (mine.length >= MAX_SOCKETS_PER_USER) mine[0].close(4002, 'too many connections');
  room.add(ws);
  stats.connections++;
  ws.alive = true;
  ws.tokens = 50; // peer-message budget, refilled every second
  send(ws, { t: 'hello', partner_online: partnerOnline(room, ws.userId) });
  for (const s of room) if (s !== ws && s.userId !== ws.userId) send(s, { t: 'presence', online: true });

  ws.on('pong', () => (ws.alive = true));
  ws.on('message', (raw, isBinary) => {
    if (isBinary || raw.length > MAX_PEER_BYTES) return;
    let msg;
    try {
      msg = JSON.parse(raw.toString('utf8'));
    } catch {
      return;
    }
    if (msg.t === 'ping') return send(ws, { t: 'pong' });
    if (msg.t === 'peer' && msg.d && typeof msg.d === 'object') {
      if (ws.tokens <= 0) return;
      ws.tokens--;
      stats.peer++;
      for (const s of room) if (s.userId !== ws.userId) send(s, { t: 'peer', d: msg.d });
    }
  });
  ws.on('close', () => {
    room.delete(ws);
    stats.connections--;
    if (room.size === 0) rooms.delete(ws.coupleId);
    else if (![...room].some((s) => s.userId === ws.userId)) for (const s of room) send(s, { t: 'presence', online: false });
  });
});

setInterval(() => {
  for (const ws of wss.clients) {
    if (!ws.alive) {
      ws.terminate();
      continue;
    }
    ws.alive = false;
    ws.ping();
  }
}, 30000).unref();
setInterval(() => {
  for (const ws of wss.clients) ws.tokens = 50;
}, 1000).unref();

server.listen(PORT, HOST, () => console.log(`relay listening on ${HOST}:${PORT}`));
module.exports = { server, verifyTicket };
