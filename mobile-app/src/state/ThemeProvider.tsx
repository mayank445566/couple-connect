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
// The free default theme is designed here (not on the server) so it can follow light/dark mode
const LIGHT: ThemeTokens = { primary: '#EE4271', background: '#FFF6F3', surface: '#FFFFFF', text: '#2B1720', bubble_mine: '#EE4271', bubble_theirs: '#FFFFFF', gradient: ['#FF4F86', '#FF8A5C'], dark: false };
const DARK: ThemeTokens = { primary: '#FF6A95', background: '#130C12', surface: '#21161F', text: '#F8EEF2', bubble_mine: '#E8467A', bubble_theirs: '#2B1E29', gradient: ['#E8467A', '#FF7B57'], dark: true };

export type Palette = ThemeTokens & {
  muted: string;
  border: string;
  onPrimary: string;
  danger: string;
  success: string;
  fontScale: number;
  /** Always at least two stops: the theme's own gradient, or one made from its primary colour */
  grad: [string, string, ...string[]];
  /** Gradient for my chat bubbles (null = use the flat bubble_mine colour the person chose) */
  bubbleGrad: [string, string, ...string[]] | null;
  /** Tinted wash behind the top of every screen */
  glow: string;
  /** Primary at low opacity, for icon circles and selected states */
  soft: string;
  /** Second surface level (inputs, chips) */
  surface2: string;
  /** CSS box-shadow for raised cards ('' on dark themes, which use a hairline border instead) */
  shadow: string;
};

export const FONT = {
  regular: 'PlusJakartaSans_400Regular',
  medium: 'PlusJakartaSans_500Medium',
  semibold: 'PlusJakartaSans_600SemiBold',
  bold: 'PlusJakartaSans_700Bold',
  extrabold: 'PlusJakartaSans_800ExtraBold',
  serif: 'Fraunces_600SemiBold',
  serifItalic: 'Fraunces_600SemiBold_Italic',
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

export function withAlpha(hex: string, a: number) {
  if (!hex.startsWith('#') || hex.length !== 7) return hex;
  const n = Math.round(a * 255).toString(16).padStart(2, '0');
  return hex + n;
}

/** Mixes a hex colour toward white (amt > 0) or black (amt < 0). */
export function shade(hex: string, amt: number) {
  if (!hex.startsWith('#') || hex.length !== 7) return hex;
  const to = amt > 0 ? 255 : 0;
  const k = Math.abs(amt);
  const ch = [1, 3, 5].map((i) => Math.round(parseInt(hex.slice(i, i + 2), 16) * (1 - k) + to * k));
  return '#' + ch.map((v) => v.toString(16).padStart(2, '0')).join('');
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
    const isDefault = !prefs?.theme_slug || prefs.theme_slug === 'default' || !selected;
    let base: ThemeTokens = isDefault ? (wantDark ? DARK : LIGHT) : selected;
    base = { ...base };
    if (prefs?.accent) base.primary = prefs.accent;
    if (prefs?.bubble_mine) base.bubble_mine = prefs.bubble_mine;
    if (prefs?.bubble_theirs) base.bubble_theirs = prefs.bubble_theirs;
    const textHex = base.text.startsWith('#') ? base.text : base.dark ? '#FFFFFF' : '#111111';
    const grad = (base.gradient && base.gradient.length >= 2 ? base.gradient : [base.primary, shade(base.primary, base.dark ? -0.25 : 0.3)]) as [string, string, ...string[]];
    // Only the default theme paints my bubbles with its gradient; a custom bubble colour always wins
    const bubbleGrad = isDefault && !prefs?.bubble_mine && base.gradient ? (base.gradient as [string, string]) : null;
    return {
      ...base,
      grad,
      bubbleGrad,
      glow: withAlpha(base.primary.startsWith('#') ? base.primary : '#FF4F86', base.dark ? 0.16 : 0.13),
      soft: withAlpha(base.primary.startsWith('#') ? base.primary : '#FF4F86', base.dark ? 0.2 : 0.11),
      surface2: base.dark ? withAlpha('#FFFFFF', 0.06) : withAlpha(textHex, 0.045),
      shadow: base.dark ? '' : '0px 6px 20px rgba(120, 30, 60, 0.08), 0px 1px 3px rgba(120, 30, 60, 0.06)',
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
