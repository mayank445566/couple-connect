import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { Pressable } from 'react-native';
import { Button, Card, ErrorBox, Header, Muted, Screen, T } from '../components/ui';
import { api } from '../lib/api';
import { AdultStatus } from '../lib/games';
import { useLoad } from '../lib/useLoad';
import { useAuth } from '../state/AuthProvider';
import { useTheme } from '../state/ThemeProvider';

/** Opt in or out of 18+ games. Each partner decides for themself; opting out stops any running 18+ game. */
export default function Adult() {
  const { c } = useTheme();
  const { refresh } = useAuth();
  const st = useLoad(() => api<{ adult: AdultStatus }>('GET', '/games').then((r) => r.adult));
  const [confirm, setConfirm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);

  const set = async (opt_in: boolean) => {
    setBusy(true);
    setErr(null);
    try {
      st.setData(await api<AdultStatus>('POST', '/games/adult-consent', { opt_in, confirm_adult: opt_in ? true : undefined }));
      await refresh();
      if (opt_in) router.back();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const s = st.data;
  return (
    <Screen>
      <Header title="18+ games" onBack={() => router.back()} />
      <Card>
        <T weight="700">Before you turn this on</T>
        <Muted>• Only for adults (18+) in a consenting relationship.</Muted>
        <Muted>• Both of you must opt in separately. Your partner is never opted in for you.</Muted>
        <Muted>• Any question can be skipped and any game stopped, by either of you, at any time.</Muted>
        <Muted>• Notifications never show 18+ content.</Muted>
        <Muted>• You can switch this off here whenever you want.</Muted>
      </Card>
      {s && !s.available ? <Muted>18+ games aren't available on this account.</Muted> : null}
      {s?.my_opt_in ? (
        <>
          <T>You're opted in.{s.partner_opt_in ? ' Your partner is too.' : " Your partner hasn't opted in yet."}</T>
          <Button title="Turn off 18+ games" variant="secondary" onPress={() => set(false)} loading={busy} />
        </>
      ) : (
        <>
          <Pressable onPress={() => setConfirm(!confirm)} style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }} accessibilityRole="checkbox" accessibilityState={{ checked: confirm }}>
            <Ionicons name={confirm ? 'checkbox' : 'square-outline'} size={24} color={c.primary} />
            <T style={{ flex: 1 }}>I confirm I'm 18 or older and choose to see adult content with my partner.</T>
          </Pressable>
          <Button title="Opt in" onPress={() => set(true)} disabled={!confirm || (s ? !s.available : true)} loading={busy} />
        </>
      )}
      {err ? <ErrorBox error={err} /> : null}
    </Screen>
  );
}
