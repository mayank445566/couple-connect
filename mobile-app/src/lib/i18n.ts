import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useSyncExternalStore } from 'react';
import { HI } from './hi';

export type Lang = 'en' | 'hi';
export const LANGS: { code: Lang; name: string }[] = [
  { code: 'en', name: 'English' },
  { code: 'hi', name: 'हिंदी' },
];

const KEY = 'cc_lang';
let current: Lang = 'en';
let loaded = false;
const listeners = new Set<() => void>();

export function getLang(): Lang {
  return current;
}

/** Loads the saved choice once at start. First run: Hindi if the phone is set to Hindi, else English. */
export async function loadLang(): Promise<Lang> {
  if (loaded) return current;
  try {
    const saved = await AsyncStorage.getItem(KEY);
    if (saved === 'en' || saved === 'hi') current = saved;
    else {
      const locale = Intl.DateTimeFormat().resolvedOptions().locale ?? '';
      current = locale.toLowerCase().startsWith('hi') ? 'hi' : 'en';
    }
  } catch {}
  loaded = true;
  listeners.forEach((l) => l());
  return current;
}

export function setLang(lang: Lang) {
  current = lang;
  AsyncStorage.setItem(KEY, lang).catch(() => {});
  listeners.forEach((l) => l());
}

function subscribe(fn: () => void) {
  listeners.add(fn);
  return () => {
    listeners.delete(fn);
  };
}

/** Re-renders when the language changes. The root uses it to redraw every screen. */
export function useLang(): Lang {
  return useSyncExternalStore(subscribe, getLang, getLang);
}

/**
 * Translate an English UI string. Unknown strings stay in English.
 * Placeholders like {name} are filled from vars.
 */
export function t(s: string, vars?: Record<string, string | number>): string {
  let out = current === 'hi' ? HI[s] ?? s : s;
  if (vars) for (const k of Object.keys(vars)) out = out.split(`{${k}}`).join(String(vars[k]));
  return out;
}

/**
 * Translates JSX text children. "Round {d.round_no} of {total}" arrives as ['Round ', 3, ' of ', 10];
 * it is looked up as "Round {0} of {1}" and the values are put back in.
 */
export function tr(children: React.ReactNode): React.ReactNode {
  if (current !== 'hi') return children;
  if (typeof children === 'string') return HI[children] ?? HI[children.trim()] ?? children;
  if (Array.isArray(children) && children.length > 1) {
    if (children.every((x) => typeof x === 'string' || typeof x === 'number')) {
      const vals: (string | number)[] = [];
      const key = children.map((x) => (typeof x === 'string' ? x : `{${vals.push(x) - 1}}`)).join('');
      const hit = HI[key] ?? HI[key.trim()];
      if (hit) return hit.replace(/\{(\d+)\}/g, (_, i) => String(vals[Number(i)]));
    }
    return children.map((x) => (typeof x === 'string' ? HI[x] ?? HI[x.trim()] ?? x : x));
  }
  return children;
}
