import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import { api, ApiError, Me, onUnauthenticated, tokenStore } from '../lib/api';

type AuthState = {
  ready: boolean;
  me: Me | null;
  signIn: (token: string, me: Me) => Promise<void>;
  signOut: (opts?: { remote?: boolean }) => Promise<void>;
  refresh: () => Promise<Me | null>;
  setMe: (me: Me) => void;
};

const Ctx = createContext<AuthState | null>(null);

export const deviceInfo = () => ({ device_name: `${Platform.OS === 'android' ? 'Android' : Platform.OS} device`, platform: Platform.OS === 'ios' ? 'ios' : Platform.OS === 'web' ? 'web' : 'android' });

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [me, setMe] = useState<Me | null>(null);

  const refresh = useCallback(async () => {
    try {
      const m = await api<Me>('GET', '/me');
      setMe(m);
      return m;
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        await tokenStore.set(null);
        setMe(null);
      }
      return null;
    }
  }, []);

  useEffect(() => {
    (async () => {
      if (await tokenStore.get()) await refresh();
      setReady(true);
    })();
    return onUnauthenticated(async () => {
      await tokenStore.set(null);
      setMe(null);
    });
  }, [refresh]);

  const signIn = useCallback(async (token: string, m: Me) => {
    await tokenStore.set(token);
    setMe(m);
  }, []);

  const signOut = useCallback(async (opts?: { remote?: boolean }) => {
    if (opts?.remote !== false) {
      try {
        await api('POST', '/auth/logout');
      } catch {
        /* signing out locally regardless */
      }
    }
    await tokenStore.set(null);
    setMe(null);
  }, []);

  const value = useMemo(() => ({ ready, me, signIn, signOut, refresh, setMe }), [ready, me, signIn, signOut, refresh]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const c = useContext(Ctx);
  if (!c) throw new Error('useAuth outside AuthProvider');
  return c;
}
