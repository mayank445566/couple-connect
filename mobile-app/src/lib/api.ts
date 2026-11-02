import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { getLang } from './i18n';

/** API base URL from app.json -> expo.extra.apiUrl (or EXPO_PUBLIC_API_URL at build time). */
export const API_URL: string = (
  process.env.EXPO_PUBLIC_API_URL ||
  (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl ||
  'http://localhost:8088'
).replace(/\/$/, '');

const TOKEN_KEY = 'cc_token';
let memoryToken: string | null = null;

/** Token lives in the Android Keystore / iOS Keychain via SecureStore. Web falls back to memory+localStorage. */
export const tokenStore = {
  async get(): Promise<string | null> {
    if (memoryToken) return memoryToken;
    if (Platform.OS === 'web') {
      memoryToken = typeof localStorage !== 'undefined' ? localStorage.getItem(TOKEN_KEY) : null;
    } else {
      memoryToken = await SecureStore.getItemAsync(TOKEN_KEY);
    }
    return memoryToken;
  },
  async set(token: string | null) {
    memoryToken = token;
    if (Platform.OS === 'web') {
      if (typeof localStorage === 'undefined') return;
      token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY);
    } else if (token) {
      await SecureStore.setItemAsync(TOKEN_KEY, token);
    } else {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
    }
  },
  current(): string | null {
    return memoryToken;
  },
};

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public fields: Record<string, string> = {},
  ) {
    super(message);
  }
  get isNetwork() {
    return this.status === 0;
  }
}

type Listener = (e: ApiError) => void;
const authListeners = new Set<Listener>();
/** Called when the server says the session is gone (401), so the app can sign out. */
export function onUnauthenticated(fn: Listener) {
  authListeners.add(fn);
  return () => {
    authListeners.delete(fn);
  };
}

export async function api<T = any>(
  method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
  path: string,
  body?: unknown,
  opts: { form?: FormData; timeoutMs?: number } = {},
): Promise<T> {
  const token = await tokenStore.get();
  const headers: Record<string, string> = { Accept: 'application/json', 'Accept-Language': getLang() };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined && !opts.form) headers['Content-Type'] = 'application/json';

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), opts.timeoutMs ?? 20000);
  let res: Response;
  try {
    res = await fetch(API_URL + path, {
      method,
      headers,
      body: opts.form ?? (body !== undefined ? JSON.stringify(body) : undefined),
      signal: ctrl.signal,
    });
  } catch {
    if (ctrl.signal.aborted) throw new ApiError(0, 'timeout', opts.form ? 'The upload took too long. Try again on a stronger connection.' : 'The server took too long to answer. Try again.');
    throw new ApiError(0, 'network', opts.form ? 'The upload did not go through. Check your connection and try again.' : 'No connection. Check your internet and try again.');
  } finally {
    clearTimeout(timer);
  }
  let json: any = null;
  try {
    json = await res.json();
  } catch {
    throw new ApiError(res.status, 'bad_response', 'Unexpected server response.');
  }
  if (!res.ok || json?.ok === false) {
    const err = new ApiError(res.status, json?.error?.code ?? 'error', json?.error?.message ?? 'Something went wrong.', json?.error?.fields ?? {});
    if (res.status === 401 && token) authListeners.forEach((l) => l(err));
    throw err;
  }
  return json.data as T;
}

/** Image/audio sources for private media need the bearer token. */
export function authedSource(url: string | null | undefined) {
  if (!url) return undefined;
  const token = tokenStore.current();
  return { uri: url, headers: token ? { Authorization: `Bearer ${token}` } : undefined };
}

/** For players that cannot send headers (video): a 15-minute signed link. */
export async function signedMediaUrl(mediaId: number): Promise<string> {
  const r = await api<{ url: string }>('GET', `/media/${mediaId}/link`);
  return r.url;
}

export async function uploadMedia(
  file: { uri: string; name: string; type: string },
  kind: 'image' | 'video' | 'audio' | 'voice' | 'file' | 'avatar',
  durationMs?: number,
): Promise<Media> {
  const form = new FormData();
  form.append('kind', kind);
  if (durationMs) form.append('duration_ms', String(Math.round(durationMs)));
  if (Platform.OS === 'web') {
    const blob = await (await fetch(file.uri)).blob();
    form.append('file', blob, file.name);
  } else {
    form.append('file', file as any);
  }
  try {
    return await api<Media>('POST', '/media', undefined, { form, timeoutMs: 180000 });
  } catch (e) {
    // One automatic retry: mobile networks often drop a single long upload
    if (e instanceof ApiError && e.code === 'network') return api<Media>('POST', '/media', undefined, { form, timeoutMs: 180000 });
    throw e;
  }
}

export function uuidv4(): string {
  // RFC 4122 v4 from crypto random values when available
  const b = new Uint8Array(16);
  if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(b);
  else for (let i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

// ---------- Types (mirror documentation/API.md) ----------

export type Me = {
  id: number;
  email: string | null;
  phone: string | null;
  display_name: string;
  dob: string;
  age: number;
  relationship_status: string;
  bio: string | null;
  avatar_url: string | null;
  email_verified: boolean;
  phone_verified: boolean;
  paired: boolean;
  couple_id: number | null;
  privacy: { last_seen_visibility: 'partner' | 'nobody'; read_receipts: boolean };
  adult_opt_in: boolean;
};

export type Partner = { id: number; display_name: string; relationship_status: string; bio: string | null; avatar_url: string | null; birthday: string | null };

export type Couple = {
  couple_id: number;
  couple_code: string;
  partner: Partner;
  paired_at: string;
  relationship_start: string | null;
  anniversary_date: string | null;
  next_meeting_at: string | null;
  days_together: number;
  settings: { disappearing_seconds: number; wallpaper: string | null };
};

export type Media = { id: number; url: string; mime: string; size: number; width: number | null; height: number | null; duration_ms: number | null; name?: string | null };

export type Message = {
  id: number;
  client_id: string;
  sender_id: number;
  mine: boolean;
  type: 'text' | 'image' | 'video' | 'audio' | 'voice' | 'file' | 'sticker' | 'gif' | 'deleted' | 'system';
  body: string | null;
  media: Media | null;
  meta: any;
  reply_to_id: number | null;
  forwarded: boolean;
  reactions: { user_id: number; emoji: string }[];
  starred: boolean;
  pinned: boolean;
  created_at: string;
  edited_at: string | null;
  deleted: boolean;
  delivered_at: string | null;
  read_at: string | null;
  expires_at: string | null;
};

export type SyncEvent = { id: number; type: string; actor_id: number | null; payload: any; at: string };

export type SyncResponse = {
  cursor: number;
  has_more: boolean;
  reset: boolean;
  events: SyncEvent[];
  messages: Message[];
  removed_message_ids: number[];
  partner: { online: boolean; typing: boolean; last_seen_at: string | null };
  unread_count: number;
};

export type Entitlements = { premium: { owned: boolean; via_partner: boolean }; themes: { owned: boolean; via_partner: boolean } };
