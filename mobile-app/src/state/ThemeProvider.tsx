import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useColorScheme } from 'react-native';
import { api } from '../lib/api';

export type ThemeTokens = {
  primary: string;
  background: string;
  surface: string;
  text: string;
  bubble_mine: string;
  bubble_theirs: string;
  gradient?: string[];
  blur?: number;
  dark: boolean;
};

export type Preferences = {
  theme_slug: string;
  color_mode: 'system' | 'light' | 'dark';
  accent: string | null;
  bubble_mine: string | null;
  bubble_theirs: string | null;
  font_scale: number;
  wallpaper: string | null;
  notifications: { messages: boolean; games: boolean; reminders: boolean; show_preview: boolean; quiet_start: string | null; quiet_end: string | null };
};

export type ThemeItem = { slug: string; name: string; premium: boolean; locked: boolean; tokens: ThemeTokens; active: boolean };

// Built-in fallbacks so the app looks right offline and before the first fetch
const LIGHT: ThemeTokens = { primary: '#E8456B', background: '#FFF7F9', surface: '#FFFFFF', text: '#2A1B22', bubble_mine: '#E8456B', bubble_theirs: '#F3E6EA', dark: false };
const DARK: ThemeTokens = { primary: '#FF6B8B', background: '#121014', surface: '#1E1B21', text: '#F3EEF1', bubble_mine: '#C23B5E', bubble_theirs: '#2A262D', dark: true };

export type Palette = ThemeTokens & {
  muted: string;
  border: string;
  onPrimary: string;
  danger: string;
  success: string;
  fontScale: number;
};

type ThemeState = {
  c: Palette;
  prefs: Preferences | null;
  themes: ThemeItem[];
  reload: () => Promise<void>;
  apply: (patch: Partial<Record<string, unknown>>) => Promise<void>;
};

const Ctx = createContext<ThemeState | null>(null);
const CACHE_KEY = 'cc_theme_cache';

function withAlpha(hex: string, a: number) {
  if (!hex.startsWith('#') || hex.length !== 7) return hex;
  const n = Math.round(a * 255).toString(16).padStart(2, '0');
  return hex + n;
}

export function ThemeProvider({ children, signedIn }: { children: React.ReactNode; signedIn: boolean }) {
  const system = useColorScheme();
  const [themes, setThemes] = useState<ThemeItem[]>([]);
  const [prefs, setPrefs] = useState<Preferences | null>(null);

  useEffect(() => {
    AsyncStorage.getItem(CACHE_KEY).then((raw) => {
      if (!raw) return;
      try {
        const cached = JSON.parse(raw);
        setThemes(cached.themes ?? []);
        setPrefs(cached.prefs ?? null);
      } catch {}
    });
  }, []);

  const reload = useCallback(async () => {
    const r = await api<{ themes: ThemeItem[]; preferences: Preferences }>('GET', '/themes');
    setThemes(r.themes);
    setPrefs(r.preferences);
    AsyncStorage.setItem(CACHE_KEY, JSON.stringify({ themes: r.themes, prefs: r.preferences }));
  }, []);

  useEffect(() => {
    if (signedIn) reload().catch(() => {});
  }, [signedIn, reload]);

  const apply = useCallback(
    async (patch: Partial<Record<string, unknown>>) => {
      const p = await api<Preferences>('PATCH', '/me/preferences', patch);
      setPrefs(p);
      setThemes((ts) => ts.map((t) => ({ ...t, active: t.slug === p.theme_slug })));
      AsyncStorage.setItem(CACHE_KEY, JSON.stringify({ themes, prefs: p }));
    },
    [themes],
  );

  const c = useMemo<Palette>(() => {
    const selected = themes.find((t) => t.slug === (prefs?.theme_slug ?? 'default'))?.tokens;
    const mode = prefs?.color_mode ?? 'system';
    const wantDark = mode === 'dark' || (mode === 'system' && system === 'dark');
    // Free "default" theme follows light/dark; named themes keep their own look
    let base: ThemeTokens = selected && prefs?.theme_slug !== 'default' ? selected : wantDark ? DARK : selected ?? LIGHT;
    base = { ...base };
    if (prefs?.accent) base.primary = prefs.accent;
    if (prefs?.bubble_mine) base.bubble_mine = prefs.bubble_mine;
    if (prefs?.bubble_theirs) base.bubble_theirs = prefs.bubble_theirs;
    const textHex = base.text.startsWith('#') ? base.text : base.dark ? '#FFFFFF' : '#111111';
    return {
      ...base,
      muted: withAlpha(textHex, 0.6),
      border: withAlpha(textHex, 0.12),
      onPrimary: '#FFFFFF',
      danger: '#E5484D',
      success: '#30A46C',
      fontScale: prefs?.font_scale ?? 1,
    };
  }, [themes, prefs, system]);

  const value = useMemo(() => ({ c, prefs, themes, reload, apply }), [c, prefs, themes, reload, apply]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useTheme() {
  const t = useContext(Ctx);
  if (!t) throw new Error('useTheme outside ThemeProvider');
  return t;
}
