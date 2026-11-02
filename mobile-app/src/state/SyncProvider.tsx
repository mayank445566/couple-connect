import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState, AppStateStatus } from 'react-native';
import { api, ApiError, Message, SyncEvent, SyncResponse, uuidv4 } from '../lib/api';
import { useAuth } from './AuthProvider';
import { play } from '../lib/sounds';

/**
 * Keeps the chat and every couple feature in sync with the server.
 * - Polls GET /sync with a cursor (3 s while the chat is open, 15 s otherwise, paused in background).
 * - Messages typed offline go to a persisted outbox and are retried with the same client_id,
 *   so the server never stores a duplicate.
 * - When the relay is configured, a WebSocket nudge triggers syncNow() instantly and polling slows down.
 *   The relay also carries live partner messages ("peer"), used by the drawing board.
 */

export type OutboxItem = {
  client_id: string;
  type: Message['type'];
  body?: string | null;
  media_id?: number;
  reply_to_id?: number | null;
  forward_of?: number;
  meta?: any;
  created_at: string;
  status: 'sending' | 'failed';
  error?: string;
  local_uri?: string;
};

type Partner = SyncResponse['partner'];
type EventListener = (e: SyncEvent) => void;

type SyncState = {
  messages: Message[];
  outbox: OutboxItem[];
  partner: Partner;
  unread: number;
  hasOlder: boolean;
  loaded: boolean;
  offline: boolean;
  setChatOpen: (open: boolean) => void;
  loadOlder: () => Promise<void>;
  send: (draft: Omit<OutboxItem, 'client_id' | 'created_at' | 'status'>) => void;
  retry: (clientId: string) => void;
  discard: (clientId: string) => void;
  upsert: (m: Message) => void;
  remove: (id: number) => void;
  syncNow: () => void;
  typing: () => void;
  on: (prefix: string, fn: EventListener) => () => void;
  relay: { connected: boolean; partnerLive: boolean; sendPeer: (d: Record<string, unknown>) => boolean };
};

const Ctx = createContext<SyncState | null>(null);
const CACHE = 'cc_msg_cache_v1';
const OUTBOX = 'cc_outbox_v1';

export function SyncProvider({ children }: { children: React.ReactNode }) {
  const { me, refresh } = useAuth();
  const paired = !!me?.paired;
  const [byId, setById] = useState<Record<number, Message>>({});
  const [outbox, setOutbox] = useState<OutboxItem[]>([]);
  const [partner, setPartner] = useState<Partner>({ online: false, typing: false, last_seen_at: null });
  const [unread, setUnread] = useState(0);
  const [hasOlder, setHasOlder] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const [offline, setOffline] = useState(false);

  const cursor = useRef(0);
  const chatOpen = useRef(false);
  const appActive = useRef(AppState.currentState === 'active');
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const inFlight = useRef(false);
  const listeners = useRef(new Map<string, Set<EventListener>>());
  const byIdRef = useRef(byId);
  byIdRef.current = byId;
  const outboxRef = useRef(outbox);
  outboxRef.current = outbox;
  const lastTyping = useRef(0);
  const ws = useRef<WebSocket | null>(null);
  const [relayUp, setRelayUp] = useState(false);
  const [partnerLive, setPartnerLive] = useState(false);
  const relayUpRef = useRef(false);
  relayUpRef.current = relayUp;

  const seenIds = useRef<Set<number> | null>(null);
  const merge = useCallback((list: Message[], removed: number[] = []) => {
    if (!list.length && !removed.length) return;
    // A soft chime for a new message from my partner (not on the first load)
    if (seenIds.current) {
      if (list.some((m) => !m.mine && !m.deleted && !seenIds.current!.has(m.id) && Date.now() - new Date(m.created_at).getTime() < 120000)) play('receive');
    } else if (list.length) seenIds.current = new Set();
    for (const m of list) seenIds.current?.add(m.id);
    setById((prev) => {
      const next = { ...prev };
      for (const m of list) next[m.id] = m;
      for (const id of removed) delete next[id];
      return next;
    });
    // A server copy replaces its pending outbox entry
    if (list.length) {
      const ids = new Set(list.map((m) => m.client_id));
      setOutbox((o) => o.filter((x) => !ids.has(x.client_id)));
    }
  }, []);

  // Persist a small cache so the chat opens instantly and offline
  useEffect(() => {
    const t = setTimeout(() => {
      const recent = Object.values(byId).sort((a, b) => b.id - a.id).slice(0, 100);
      AsyncStorage.setItem(CACHE, JSON.stringify({ couple: me?.couple_id, messages: recent, cursor: cursor.current }));
    }, 800);
    return () => clearTimeout(t);
  }, [byId, me?.couple_id]);
  useEffect(() => {
    AsyncStorage.setItem(OUTBOX, JSON.stringify(outbox));
  }, [outbox]);

  const emit = (e: SyncEvent) => {
    if (e.type === 'game.invited' && e.actor_id !== me?.id) play('invite');
    listeners.current.forEach((set, prefix) => {
      if (e.type.startsWith(prefix)) set.forEach((fn) => fn(e));
    });
  };

  const ackReceipts = useCallback(async (list: Message[]) => {
    const fromPartner = list.filter((m) => !m.mine && !m.deleted);
    if (!fromPartner.length) return;
    const maxId = Math.max(...fromPartner.map((m) => m.id));
    try {
      if (chatOpen.current && appActive.current && fromPartner.some((m) => !m.read_at)) {
        const r = await api<{ unread_count: number }>('POST', '/messages/read', { up_to_id: maxId });
        setUnread(r.unread_count);
      } else if (fromPartner.some((m) => !m.delivered_at)) {
        await api('POST', '/messages/delivered', { up_to_id: maxId });
      }
    } catch {}
  }, []);

  const loadInitial = useCallback(async () => {
    const r = await api<{ messages: Message[]; has_more: boolean; cursor: number; unread_count: number }>('GET', '/messages?limit=40');
    cursor.current = r.cursor;
    setById(Object.fromEntries(r.messages.map((m) => [m.id, m])));
    setHasOlder(r.has_more);
    setUnread(r.unread_count);
    setLoaded(true);
    ackReceipts(r.messages);
  }, [ackReceipts]);

  const flushOutbox = useCallback(async () => {
    for (const item of outboxRef.current) {
      if (item.status !== 'sending') continue;
      try {
        const m = await api<Message>('POST', '/messages', {
          client_id: item.client_id,
          type: item.type,
          body: item.body ?? undefined,
          media_id: item.media_id,
          reply_to_id: item.reply_to_id ?? undefined,
          forward_of: item.forward_of,
          meta: item.meta,
        });
        merge([m]);
      } catch (e) {
        if (e instanceof ApiError && !e.isNetwork && e.status !== 429) {
          // Permanent failure (validation, unpaired): keep it visible with a retry button
          setOutbox((o) => o.map((x) => (x.client_id === item.client_id ? { ...x, status: 'failed', error: e.message } : x)));
        } else {
          break; // offline: try again on the next tick, keeping order
        }
      }
    }
  }, [merge]);

  const tick = useCallback(async () => {
    if (!paired || inFlight.current) return;
    inFlight.current = true;
    try {
      await flushOutbox();
      let more = true;
      while (more) {
        const r = await api<SyncResponse>('GET', `/sync?since=${cursor.current}`);
        if (r.reset) {
          await loadInitial();
          break;
        }
        cursor.current = r.cursor;
        merge(r.messages, r.removed_message_ids);
        setPartner(r.partner);
        setUnread(r.unread_count);
        r.events.forEach(emit);
        if (r.events.some((e) => e.type === 'couple.unpaired')) refresh();
        ackReceipts(r.messages);
        more = r.has_more;
      }
      setOffline(false);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'not_paired') refresh();
      if (e instanceof ApiError && e.isNetwork) setOffline(true);
    } finally {
      inFlight.current = false;
    }
  }, [paired, flushOutbox, loadInitial, merge, ackReceipts, refresh]);

  const schedule = useCallback(() => {
    if (timer.current) clearTimeout(timer.current);
    if (!paired || !appActive.current) return;
    timer.current = setTimeout(async () => {
      await tick();
      schedule();
    }, relayUpRef.current ? (chatOpen.current ? 15000 : 30000) : chatOpen.current ? 3000 : 15000);
  }, [paired, tick]);

  const syncNow = useCallback(() => {
    tick().finally(schedule);
  }, [tick, schedule]);

  // Boot: cached messages + outbox, then live data
  useEffect(() => {
    if (!paired) {
      setById({});
      setLoaded(false);
      cursor.current = 0;
      return;
    }
    let cancelled = false;
    (async () => {
      const [cache, ob] = await Promise.all([AsyncStorage.getItem(CACHE), AsyncStorage.getItem(OUTBOX)]);
      if (cancelled) return;
      try {
        const c = cache ? JSON.parse(cache) : null;
        if (c && c.couple === me?.couple_id) {
          setById(Object.fromEntries((c.messages as Message[]).map((m) => [m.id, m])));
          setLoaded(true);
        }
        if (ob) setOutbox((JSON.parse(ob) as OutboxItem[]).map((x) => ({ ...x, status: x.status === 'failed' ? 'failed' : 'sending' })));
      } catch {}
      try {
        await loadInitial();
        setOffline(false);
      } catch (e) {
        if (e instanceof ApiError && e.isNetwork) setOffline(true);
      }
      syncNow();
    })();
    return () => {
      cancelled = true;
      if (timer.current) clearTimeout(timer.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [paired, me?.couple_id]);

  useEffect(() => {
    const sub = AppState.addEventListener('change', (s: AppStateStatus) => {
      appActive.current = s === 'active';
      if (appActive.current) syncNow();
      else if (timer.current) clearTimeout(timer.current);
    });
    return () => sub.remove();
  }, [syncNow]);

  // Relay: instant nudges and live partner messages. Polling keeps working if it is down.
  useEffect(() => {
    if (!paired) return;
    let stopped = false;
    let retry: ReturnType<typeof setTimeout> | null = null;
    let attempt = 0;
    const connect = async () => {
      if (stopped || !appActive.current) return;
      try {
        const t = await api<{ url: string | null; ticket: string | null }>('POST', '/realtime/ticket');
        if (!t.url || !t.ticket || stopped) return; // relay not configured on this server
        const sock = new WebSocket(`${t.url}?ticket=${encodeURIComponent(t.ticket)}`);
        ws.current = sock;
        sock.onopen = () => {
          attempt = 0;
          setRelayUp(true);
          syncNow();
        };
        sock.onmessage = (ev) => {
          let m: any;
          try {
            m = JSON.parse(String(ev.data));
          } catch {
            return;
          }
          if (m.t === 'nudge') {
            if (m.type === 'call.signal') emit({ id: 0, type: 'call.signal', actor_id: null, payload: { call_id: m.call_id }, at: new Date().toISOString() });
            else syncNow();
          } else if (m.t === 'peer') emit({ id: 0, type: 'peer.' + String(m.d?.kind ?? 'msg'), actor_id: null, payload: m.d, at: new Date().toISOString() });
          else if (m.t === 'hello') setPartnerLive(!!m.partner_online);
          else if (m.t === 'presence') setPartnerLive(!!m.online);
        };
        sock.onclose = () => {
          if (ws.current === sock) ws.current = null;
          setRelayUp(false);
          setPartnerLive(false);
          if (!stopped) retry = setTimeout(connect, Math.min(30000, 1000 * 2 ** attempt++));
        };
      } catch {
        if (!stopped) retry = setTimeout(connect, Math.min(30000, 1000 * 2 ** attempt++));
      }
    };
    connect();
    const sub = AppState.addEventListener('change', (st) => {
      if (st === 'active' && !ws.current) connect();
      if (st !== 'active') ws.current?.close();
    });
    return () => {
      stopped = true;
      if (retry) clearTimeout(retry);
      sub.remove();
      ws.current?.close();
      ws.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [paired, me?.couple_id]);

  const sendPeer = useCallback((d: Record<string, unknown>) => {
    const sock = ws.current;
    if (!sock || sock.readyState !== 1) return false;
    sock.send(JSON.stringify({ t: 'peer', d }));
    return true;
  }, []);
  const relay = useMemo(() => ({ connected: relayUp, partnerLive, sendPeer }), [relayUp, partnerLive, sendPeer]);

  const setChatOpen = useCallback(
    (open: boolean) => {
      chatOpen.current = open;
      if (open) {
        ackReceipts(Object.values(byIdRef.current));
        syncNow();
      } else schedule();
    },
    [ackReceipts, syncNow, schedule],
  );

  const loadOlder = useCallback(async () => {
    const ids = Object.keys(byIdRef.current).map(Number);
    if (!ids.length) return;
    const r = await api<{ messages: Message[]; has_more: boolean }>('GET', `/messages?limit=40&before=${Math.min(...ids)}`);
    merge(r.messages);
    setHasOlder(r.has_more);
  }, [merge]);

  const send = useCallback<SyncState['send']>(
    (draft) => {
      const item: OutboxItem = { ...draft, client_id: uuidv4(), created_at: new Date().toISOString(), status: 'sending' };
      setOutbox((o) => [...o, item]);
      outboxRef.current = [...outboxRef.current, item];
      play('send');
      syncNow();
    },
    [syncNow],
  );

  const retry = useCallback(
    (clientId: string) => {
      setOutbox((o) => o.map((x) => (x.client_id === clientId ? { ...x, status: 'sending', error: undefined } : x)));
      outboxRef.current = outboxRef.current.map((x) => (x.client_id === clientId ? { ...x, status: 'sending' } : x));
      syncNow();
    },
    [syncNow],
  );

  const discard = useCallback((clientId: string) => setOutbox((o) => o.filter((x) => x.client_id !== clientId)), []);
  const upsert = useCallback((m: Message) => merge([m]), [merge]);
  const remove = useCallback((id: number) => merge([], [id]), [merge]);

  const typing = useCallback(() => {
    const now = Date.now();
    if (now - lastTyping.current < 4000) return;
    lastTyping.current = now;
    api('POST', '/presence/typing', { typing: true }).catch(() => {});
  }, []);

  const on = useCallback((prefix: string, fn: EventListener) => {
    if (!listeners.current.has(prefix)) listeners.current.set(prefix, new Set());
    listeners.current.get(prefix)!.add(fn);
    return () => {
      listeners.current.get(prefix)?.delete(fn);
    };
  }, []);

  const messages = useMemo(() => Object.values(byId).sort((a, b) => b.id - a.id), [byId]);

  const value = useMemo(
    () => ({ messages, outbox, partner, unread, hasOlder, loaded, offline, setChatOpen, loadOlder, send, retry, discard, upsert, remove, syncNow, typing, on, relay }),
    [messages, outbox, partner, unread, hasOlder, loaded, offline, setChatOpen, loadOlder, send, retry, discard, upsert, remove, syncNow, typing, on, relay],
  );
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useSync() {
  const s = useContext(Ctx);
  if (!s) throw new Error('useSync outside SyncProvider');
  return s;
}

/** Re-run a loader whenever matching sync events arrive (e.g. 'game.', 'hub.'). */
export function useSyncEvent(prefix: string, fn: EventListener) {
  const { on } = useSync();
  const ref = useRef(fn);
  ref.current = fn;
  useEffect(() => on(prefix, (e) => ref.current(e)), [on, prefix]);
}
