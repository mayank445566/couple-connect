import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { View } from 'react-native';
import { Button, Card, ErrorBox, Header, Hero, Muted, Row, Screen, SkeletonList, T} from '../components/ui';
import { api, Entitlements } from '../lib/api';
import { buy, PaymentCancelled } from '../lib/payments';
import { useLoad } from '../lib/useLoad';
import { useSyncEvent } from '../state/SyncProvider';
import { useTheme } from '../state/ThemeProvider';

type Product = { sku: string; title: string; description: string; price_display: string; grants: string[]; one_time: boolean; owned: boolean };

const PERKS: [React.ComponentProps<typeof Ionicons>['name'], string][] = [
  ['flame-outline', '11 private 18+ games for couples, with consent controls'],
  ['color-palette-outline', 'Every premium theme and custom bubble colours'],
  ['people-outline', 'Unlocks for both of you with one purchase'],
  ['infinite-outline', 'Pay once. No subscription, no renewals'],
];

export default function Premium() {
  const { c, reload: reloadThemes } = useTheme();
  const store = useLoad(() => api<{ products: Product[]; entitlements: Entitlements }>('GET', '/store/products'));
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<unknown>(null);
  useSyncEvent('premium.', () => store.reload());

  const product = store.data?.products.find((p) => p.sku === 'premium_lifetime') ?? store.data?.products[0];
  const ent = store.data?.entitlements.premium;

  const purchase = async () => {
    if (!product) return;
    setBusy(true);
    setErr(null);
    setMsg(null);
    try {
      await buy(product.sku, c.primary);
      setMsg('Unlocked! Thank you 💖');
      await store.reload();
      reloadThemes().catch(() => {});
    } catch (e) {
      if (e instanceof PaymentCancelled) setMsg('Payment cancelled. You were not charged.');
      else setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const restore = async () => {
    setErr(null);
    try {
      await api('POST', '/store/restore');
      await store.reload();
      setMsg('Purchases checked.');
    } catch (e) {
      setErr(e);
    }
  };

  return (
    <Screen>
      <Header title="Premium" onBack={() => router.back()} />
      <Hero style={{ alignItems: 'center' }}>
        <Ionicons name="diamond" size={40} color="#fff" />
        <T color="#fff" size={24} weight="800">{product?.title ?? 'Couple Connect Premium'}</T>
        <T color="#fff" size={34} weight="800">{product?.price_display ?? '₹199'}</T>
        <T color="#ffffffCC">one-time · lifetime</T>
      </Hero>
      {PERKS.map(([icon, text]) => (
        <Row key={text}>
          <Ionicons name={icon} size={22} color={c.primary} />
          <T style={{ flex: 1 }}>{text}</T>
        </Row>
      ))}
      {!store.data ? store.error ? <ErrorBox error={store.error} onRetry={store.reload} /> : <SkeletonList rows={1} /> : null}
      {ent?.owned ? (
        <Card style={{ borderColor: c.success, borderWidth: 1 }}>
          <Row>
            <Ionicons name="checkmark-circle" size={24} color={c.success} />
            <T style={{ flex: 1 }}>{ent.via_partner ? 'Unlocked by your partner. Enjoy!' : 'You own Premium. Enjoy!'}</T>
          </Row>
        </Card>
      ) : product ? (
        <Button title={`Unlock for ${product.price_display}`} onPress={purchase} loading={busy} />
      ) : null}
      {msg ? <Muted center>{msg}</Muted> : null}
      {err ? <ErrorBox error={err} /> : null}
      <Button title="Restore purchases" variant="ghost" onPress={restore} />
      <View style={{ gap: 4 }}>
        <Muted center>Secure payment by Razorpay (UPI, cards, netbanking, wallets). Your payment details never touch our servers.</Muted>
        <Muted center>18+ games also need both partners to be 18+ and to opt in.</Muted>
      </View>
    </Screen>
  );
}
