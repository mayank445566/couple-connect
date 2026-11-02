import { Ionicons } from '@expo/vector-icons';
import { File, Paths } from 'expo-file-system';
import { router } from 'expo-router';
import * as Sharing from 'expo-sharing';
import React, { useState } from 'react';
import { Alert, Platform, Switch, View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Muted, Pill, Row, Screen, T, errorText, fieldErrors, timeAgo } from '../components/ui';
import { api, Me } from '../lib/api';
import { useLoad } from '../lib/useLoad';
import { useAuth } from '../state/AuthProvider';
import { useTheme } from '../state/ThemeProvider';

type Sess = { id: number; device_name: string | null; platform: string | null; ip: string | null; created_at: string; last_used_at: string | null; current: boolean };

export default function Settings() {
  const { c, prefs, apply } = useTheme();
  const { me, setMe, signOut, refresh } = useAuth();
  const sessions = useLoad(() => api<Sess[] | { sessions: Sess[] }>('GET', '/auth/sessions'));
  const [panel, setPanel] = useState<null | 'password' | 'phone' | 'unpair' | 'delete'>(null);
  const [err, setErr] = useState<unknown>(null);
  const list = sessions.data ? (Array.isArray(sessions.data) ? sessions.data : sessions.data.sessions) : [];

  const patchMe = async (patch: Record<string, unknown>) => {
    try {
      setMe(await api<Me>('PATCH', '/me', patch));
    } catch (e) {
      Alert.alert('Could not save', errorText(e));
    }
  };
  const patchPrefs = (patch: Record<string, unknown>) => apply(patch).catch((e) => Alert.alert('Could not save', errorText(e)));

  const exportData = async () => {
    try {
      const data = await api('GET', '/me/export');
      const json = JSON.stringify(data, null, 2);
      if (Platform.OS === 'web') {
        const url = URL.createObjectURL(new Blob([json], { type: 'application/json' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = 'couple-connect-export.json';
        a.click();
        URL.revokeObjectURL(url);
        return;
      }
      const f = new File(Paths.cache, 'couple-connect-export.json');
      if (f.exists) f.delete();
      f.create();
      f.write(json);
      await Sharing.shareAsync(f.uri, { mimeType: 'application/json', dialogTitle: 'Your Couple Connect data' });
    } catch (e) {
      Alert.alert('Export failed', errorText(e));
    }
  };

  const n = prefs?.notifications;

  return (
    <Screen>
      <Header title="Settings" onBack={() => router.back()} />

      {me?.paired ? (
        <Card>
          <T weight="700">Privacy</T>
          <Toggle label="Show my last seen to my partner" value={me.privacy.last_seen_visibility === 'partner'} onChange={(v) => patchMe({ last_seen_visibility: v ? 'partner' : 'nobody' })} />
          <Toggle label="Send read receipts" hint="If off, your partner won't see when you've read messages." value={me.privacy.read_receipts} onChange={(v) => patchMe({ read_receipts: v })} />
        </Card>
      ) : null}

      {n ? (
        <Card>
          <T weight="700">Notifications</T>
          <Toggle label="Messages" value={n.messages} onChange={(v) => patchPrefs({ notify_messages: v })} />
          <Toggle label="Show message text in notifications" hint="Off by default so nothing private shows on your lock screen." value={n.show_preview} onChange={(v) => patchPrefs({ show_preview: v })} />
          <Toggle label="Game invites" value={n.games} onChange={(v) => patchPrefs({ notify_games: v })} />
          <Toggle label="Reminders & countdowns" value={n.reminders} onChange={(v) => patchPrefs({ notify_reminders: v })} />
          <QuietHours start={n.quiet_start} end={n.quiet_end} onSave={(s, e) => patchPrefs({ quiet_start: s, quiet_end: e })} />
        </Card>
      ) : null}

      <Card>
        <T weight="700">Account</T>
        <Muted>{me?.email ?? 'No email'}{me?.phone ? ` · ${me.phone}` : ''}</Muted>
        <Row style={{ flexWrap: 'wrap' }} gap={8}>
          <Pill label="Change password" active={panel === 'password'} onPress={() => setPanel(panel === 'password' ? null : 'password')} />
          <Pill label={me?.phone ? 'Change phone' : 'Add phone'} active={panel === 'phone'} onPress={() => setPanel(panel === 'phone' ? null : 'phone')} />
          <Pill label="Download my data" icon="download-outline" onPress={exportData} />
        </Row>
        {panel === 'password' ? <PasswordPanel onDone={() => setPanel(null)} /> : null}
        {panel === 'phone' ? <PhonePanel onDone={async () => { setPanel(null); await refresh(); }} /> : null}
      </Card>

      <Card>
        <T weight="700">Signed-in devices</T>
        {list.map((s) => (
          <Row key={s.id}>
            <Ionicons name={s.platform === 'web' ? 'desktop-outline' : 'phone-portrait-outline'} size={20} color={c.muted} />
            <View style={{ flex: 1 }}>
              <T>{s.device_name ?? s.platform ?? 'Device'}{s.current ? ' (this device)' : ''}</T>
              <Muted>Active {timeAgo(s.last_used_at ?? s.created_at)}</Muted>
            </View>
            {!s.current ? (
              <Button title="Sign out" variant="ghost" onPress={async () => { try { await api('DELETE', `/auth/sessions/${s.id}`); sessions.reload(); } catch (e) { setErr(e); } }} />
            ) : null}
          </Row>
        ))}
        <Button title="Sign out everywhere" variant="secondary" onPress={() => Alert.alert('Sign out everywhere?', 'Every device, including this one, will be signed out.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Sign out', style: 'destructive', onPress: async () => { await api('POST', '/auth/logout-all').catch(() => {}); signOut({ remote: false }); } }])} />
      </Card>

      {err ? <ErrorBox error={err} /> : null}

      <Card>
        <T weight="700">Safety</T>
        <Row style={{ flexWrap: 'wrap' }} gap={8}>
          <Pill label="Report a problem" icon="flag-outline" onPress={() => router.push('/report')} />
          {me?.paired ? <Pill label="Unpair" icon="heart-dislike-outline" active={panel === 'unpair'} onPress={() => setPanel(panel === 'unpair' ? null : 'unpair')} /> : null}
          <Pill label="Delete account" icon="trash-outline" active={panel === 'delete'} onPress={() => setPanel(panel === 'delete' ? null : 'delete')} />
        </Row>
        {panel === 'unpair' ? <DangerPanel kind="unpair" onDone={async () => { setPanel(null); await refresh(); }} /> : null}
        {panel === 'delete' ? <DangerPanel kind="delete" onDone={() => signOut({ remote: false })} /> : null}
      </Card>

      <Button title="Sign out" variant="ghost" onPress={() => signOut()} />
      <Muted center>Messages are encrypted in transit (HTTPS) and media is encrypted on our servers. Chats are not end-to-end encrypted.</Muted>
    </Screen>
  );
}

function Toggle({ label, hint, value, onChange }: { label: string; hint?: string; value: boolean; onChange: (v: boolean) => void }) {
  const { c } = useTheme();
  return (
    <Row>
      <View style={{ flex: 1 }}>
        <T>{label}</T>
        {hint ? <Muted>{hint}</Muted> : null}
      </View>
      <Switch value={value} onValueChange={onChange} trackColor={{ true: c.primary, false: c.border }} thumbColor="#fff" />
    </Row>
  );
}

function QuietHours({ start, end, onSave }: { start: string | null; end: string | null; onSave: (s: string, e: string) => void }) {
  const [s, setS] = useState(start ?? '');
  const [e, setE] = useState(end ?? '');
  const ok = /^([01]\d|2[0-3]):[0-5]\d$/;
  return (
    <View style={{ gap: 6 }}>
      <T>Quiet hours</T>
      <Row>
        <View style={{ flex: 1 }}><Field value={s} onChangeText={setS} placeholder="23:00" keyboardType="numbers-and-punctuation" /></View>
        <T>to</T>
        <View style={{ flex: 1 }}><Field value={e} onChangeText={setE} placeholder="07:00" keyboardType="numbers-and-punctuation" /></View>
        <Button title="Save" variant="ghost" disabled={!ok.test(s) || !ok.test(e) || (s === start && e === end)} onPress={() => onSave(s, e)} />
      </Row>
    </View>
  );
}

function PasswordPanel({ onDone }: { onDone: () => void }) {
  const [cur, setCur] = useState('');
  const [next, setNext] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  return (
    <View style={{ gap: 8 }}>
      <Field label="Current password" value={cur} onChangeText={setCur} secureTextEntry error={f.current_password} />
      <Field label="New password" value={next} onChangeText={setNext} secureTextEntry error={f.new_password} />
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Button
        title="Change password"
        loading={busy}
        disabled={!cur || !next}
        onPress={async () => {
          setBusy(true);
          setErr(null);
          try {
            await api('POST', '/me/password', { current_password: cur, new_password: next });
            Alert.alert('Password changed', 'Other devices were signed out.');
            onDone();
          } catch (e) {
            setErr(e);
          } finally {
            setBusy(false);
          }
        }}
      />
    </View>
  );
}

function PhonePanel({ onDone }: { onDone: () => void }) {
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
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
  return (
    <View style={{ gap: 8 }}>
      <Field label="Mobile number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" error={f.phone} editable={!sent} />
      {sent ? <Field label="6-digit code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} error={f.code} /> : null}
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      {!sent ? (
        <Button title="Send code" loading={busy} disabled={phone.trim().length < 10} onPress={() => run(async () => { await api('POST', '/auth/phone/request-otp', { phone: phone.trim() }); setSent(true); })} />
      ) : (
        <Button title="Verify number" loading={busy} disabled={code.length !== 6} onPress={() => run(async () => { await api('POST', '/me/phone', { phone: phone.trim(), code }); onDone(); })} />
      )}
    </View>
  );
}

function DangerPanel({ kind, onDone }: { kind: 'unpair' | 'delete'; onDone: () => void }) {
  const [password, setPassword] = useState('');
  const [block, setBlock] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const text =
    kind === 'unpair'
      ? 'This ends the connection for both of you. Your shared chat and lists will no longer be available to either of you.'
      : 'Your account is scheduled for deletion and you are signed out. Sign in within 7 days to cancel. After that, everything is permanently erased.';
  return (
    <View style={{ gap: 8 }}>
      <Muted>{text}</Muted>
      <Field label="Confirm with your password" value={password} onChangeText={setPassword} secureTextEntry error={f.password} />
      {kind === 'unpair' ? <Toggle label="Also block this person from pairing with me again" value={block} onChange={setBlock} /> : null}
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Button
        title={kind === 'unpair' ? 'Unpair' : 'Delete my account'}
        variant="danger"
        loading={busy}
        disabled={!password}
        onPress={async () => {
          setBusy(true);
          setErr(null);
          try {
            await api('POST', kind === 'unpair' ? '/couple/unpair' : '/me/delete', kind === 'unpair' ? { password, block } : { password });
            onDone();
          } catch (e) {
            setErr(e);
          } finally {
            setBusy(false);
          }
        }}
      />
    </View>
  );
}
