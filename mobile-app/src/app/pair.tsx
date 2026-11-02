import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, router } from 'expo-router';
import * as Clipboard from 'expo-clipboard';
import React, { useCallback, useEffect, useState } from 'react';
import { Share, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { Avatar, Button, Card, ErrorBox, Field, Muted, Pill, Row, Screen, T, fieldErrors } from '../components/ui';
import { api, Partner } from '../lib/api';
import { useAuth } from '../state/AuthProvider';
import { useTheme } from '../state/ThemeProvider';

type Invite = { request_id: number; code: string; qr_payload: string; share_text: string; expires_at: string };
type Requests = {
  open_invite: { request_id: number; expires_at: string } | null;
  incoming: { request_id: number; from: Partner; joined_at: string }[];
  outgoing: { request_id: number; status: string; to: Partner } | null;
};

function useCountdown(iso?: string | null) {
  const [now, setNow] = useState(Date.now());
  useEffect(() => {
    if (!iso) return;
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, [iso]);
  if (!iso) return null;
  const s = Math.max(0, Math.floor((new Date(iso).getTime() - now) / 1000));
  return { left: s, label: `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}` };
}

export default function Pair() {
  const { me, refresh, signOut } = useAuth();
  const { c } = useTheme();
  const [tab, setTab] = useState<'share' | 'enter'>('share');
  const [invite, setInvite] = useState<Invite | null>(null);
  const [reqs, setReqs] = useState<Requests | null>(null);
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const cd = useCountdown(invite?.expires_at);

  const load = useCallback(async () => {
    try {
      setReqs(await api<Requests>('GET', '/couple/requests'));
      // The partner may have accepted on their side
      const m = await refresh();
      if (m?.paired) router.replace('/');
    } catch {}
  }, [refresh]);

  useFocusEffect(
    useCallback(() => {
      load();
      const t = setInterval(load, 4000);
      return () => clearInterval(t);
    }, [load]),
  );

  const run = async (fn: () => Promise<void>) => {
    setBusy(true);
    setErr(null);
    try {
      await fn();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const makeInvite = () => run(async () => setInvite(await api<Invite>('POST', '/couple/invite')));
  const join = () => run(async () => { await api('POST', '/couple/join', { code: code.trim() }); setCode(''); await load(); });
  const act = (id: number, action: 'accept' | 'reject' | 'cancel') =>
    run(async () => {
      await api('POST', `/couple/requests/${id}/${action}`);
      if (action === 'accept') {
        await refresh();
        router.replace('/');
      } else await load();
    });

  return (
    <Screen>
      <View style={{ alignItems: 'center', gap: 6, marginTop: 12, marginBottom: 4 }}>
        <Row gap={0}>
          <Avatar url={me?.avatar_url} name={me?.display_name} size={72} ring />
          <View style={{ zIndex: 2, marginHorizontal: -12, width: 40, height: 40, borderRadius: 20, backgroundColor: c.surface, alignItems: 'center', justifyContent: 'center', boxShadow: c.shadow || undefined } as any}>
            <Ionicons name="heart" size={22} color={c.primary} />
          </View>
          <View style={{ width: 82, height: 82, borderRadius: 41, borderWidth: 2.5, borderStyle: 'dashed', borderColor: c.primary + '88', alignItems: 'center', justifyContent: 'center' }}>
            <T serif size={30} color={c.primary}>?</T>
          </View>
        </Row>
        <T serif size={28} center style={{ marginTop: 10 }}>Connect with your partner</T>
      </View>
      <Muted>Hi {me?.display_name}. One of you shares a code, the other enters it. Then the person who shared approves. Nobody can join without both of you agreeing.</Muted>

      {reqs?.incoming.map((r) => (
        <Card key={r.request_id} style={{ borderColor: c.primary, borderWidth: 2 }}>
          <T weight="700">Someone used your code</T>
          <Row>
            <Avatar url={r.from.avatar_url} name={r.from.display_name} />
            <View style={{ flex: 1 }}>
              <T weight="600">{r.from.display_name}</T>
              {r.from.bio ? <Muted>{r.from.bio}</Muted> : null}
            </View>
          </Row>
          <Muted>Only accept if this is really your partner.</Muted>
          <Row>
            <Button title="Accept" style={{ flex: 1 }} onPress={() => act(r.request_id, 'accept')} loading={busy} />
            <Button title="Reject" variant="secondary" style={{ flex: 1 }} onPress={() => act(r.request_id, 'reject')} />
          </Row>
        </Card>
      ))}

      {reqs?.outgoing && reqs.outgoing.status === 'pending_approval' ? (
        <Card>
          <T weight="700">Waiting for {reqs.outgoing.to.display_name}</T>
          <Muted>They need to approve on their phone. This screen updates on its own.</Muted>
          <Button title="Cancel request" variant="ghost" onPress={() => act(reqs.outgoing!.request_id, 'cancel')} />
        </Card>
      ) : null}

      <Row gap={8}>
        <Pill label="Share my code" active={tab === 'share'} onPress={() => setTab('share')} icon="qr-code-outline" />
        <Pill label="Enter a code" active={tab === 'enter'} onPress={() => setTab('enter')} icon="keypad-outline" />
      </Row>

      {tab === 'share' ? (
        invite && cd && cd.left > 0 ? (
          <Card style={{ alignItems: 'center', gap: 12 }}>
            <T serif size={44} color={c.primary} style={{ letterSpacing: 10 }}>{invite.code}</T>
            <View style={{ backgroundColor: '#fff', padding: 12, borderRadius: 16 }}>
              <QRCode value={invite.qr_payload} size={180} />
            </View>
            <Muted>Expires in {cd.label}. Works once.</Muted>
            <Row>
              <Button title="Copy" variant="secondary" icon="copy-outline" onPress={() => Clipboard.setStringAsync(invite.code)} />
              <Button title="Share" icon="share-outline" onPress={() => Share.share({ message: invite.share_text })} />
            </Row>
            <Button title="New code" variant="ghost" onPress={makeInvite} />
          </Card>
        ) : (
          <Button title={invite ? 'Code expired, get a new one' : 'Get my code'} onPress={makeInvite} loading={busy} icon="qr-code-outline" />
        )
      ) : (
        <Card>
          <Field label="Partner's 6-digit code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} error={f.code} />
          <Button title="Connect" onPress={join} loading={busy} disabled={code.trim().length !== 6} />
          <Button title="Scan their QR code" variant="secondary" icon="camera-outline" onPress={() => router.push('/scan')} />
        </Card>
      )}

      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Row style={{ justifyContent: 'center' }}>
        <Button title="Settings" variant="ghost" onPress={() => router.push('/settings')} />
        <Button title="Sign out" variant="ghost" onPress={() => signOut()} />
      </Row>
    </Screen>
  );
}
