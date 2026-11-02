import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { Button, Card, Empty, ErrorBox, Field, Header, Muted, Pill, Row, Screen, SkeletonList, T, errorText, fieldErrors, timeAgo } from '../components/ui';
import { api } from '../lib/api';
import { fmtDate, toIso } from '../lib/dates';
import { useLoad } from '../lib/useLoad';
import { useSyncEvent } from '../state/SyncProvider';
import { useTheme } from '../state/ThemeProvider';
import { t } from '../lib/i18n';

type Kind = 'letter' | 'note' | 'journal' | 'wish';
type Note = { id: number; kind: Kind; title: string | null; body: string; from_me: boolean; deliver_at: string | null; opened: boolean; created_at: string };

const KINDS: { k: Kind; label: string; hint: string }[] = [
  { k: 'letter', label: 'Letters', hint: 'Write now, deliver later. Perfect for "open when…" letters.' },
  { k: 'note', label: 'Shared notes', hint: 'Lists and notes you both can read.' },
  { k: 'journal', label: 'Journal', hint: 'Your shared diary of days apart and together.' },
  { k: 'wish', label: 'Wishes', hint: 'Things you wish for: gifts, dates, dreams.' },
];

export default function Notes() {
  const { c } = useTheme();
  const [kind, setKind] = useState<Kind>('letter');
  const data = useLoad(() => api<{ notes: Note[]; sealed_letters_waiting: number }>('GET', `/notes?kind=${kind}`), [kind]);
  const [open, setOpen] = useState<Note | null>(null);
  const [writing, setWriting] = useState(false);
  useSyncEvent('note.', () => data.reload());

  const openNote = async (n: Note) => {
    try {
      setOpen(await api<Note>('GET', `/notes/${n.id}`));
      if (!n.from_me && !n.opened) data.reload();
    } catch (e) {
      Alert.alert(t('Could not open'), errorText(e));
    }
  };

  if (open) {
    return (
      <Screen>
        <Header title={open.title || (open.kind === 'letter' ? 'A letter' : 'Note')} onBack={() => setOpen(null)} />
        <Muted>{open.from_me ? 'From you' : 'From your partner'} · {fmtDate(open.created_at)}</Muted>
        <Card>
          <T raw size={16} style={{ lineHeight: 24 }}>{open.body}</T>
        </Card>
        {open.from_me ? (
          <Button
            title="Delete"
            variant="ghost"
            onPress={async () => {
              try {
                await api('DELETE', `/notes/${open.id}`);
                setOpen(null);
                data.reload();
              } catch (e) {
                Alert.alert(t('Could not delete'), errorText(e));
              }
            }}
          />
        ) : null}
      </Screen>
    );
  }

  return (
    <Screen refreshing={data.refreshing} onRefresh={data.onRefresh}>
      <Header title="Letters & notes" onBack={() => router.back()} right={!writing ? <Button title="Write" icon="create-outline" variant="ghost" onPress={() => setWriting(true)} /> : null} />
      <Row style={{ flexWrap: 'wrap' }} gap={8}>
        {KINDS.map((k) => (
          <Pill key={k.k} label={k.label} active={kind === k.k} onPress={() => { setKind(k.k); setWriting(false); }} />
        ))}
      </Row>
      <Muted>{KINDS.find((k) => k.k === kind)!.hint}</Muted>
      {writing ? <Compose kind={kind} onDone={() => { setWriting(false); data.reload(); }} onCancel={() => setWriting(false)} /> : null}
      {kind === 'letter' && data.data?.sealed_letters_waiting ? (
        <Card style={{ borderColor: c.primary, borderWidth: 1 }}>
          <Row>
            <T size={26}>💌</T>
            <T style={{ flex: 1 }}>{t(data.data.sealed_letters_waiting === 1 ? '1 sealed letter is waiting for you. It will open on its delivery date.' : '{n} sealed letters are waiting for you. They\'ll open on their delivery date.', { n: data.data.sealed_letters_waiting })}</T>
          </Row>
        </Card>
      ) : null}
      {data.error ? <ErrorBox error={data.error} onRetry={data.reload} /> : null}
      {!data.data && !data.error ? <SkeletonList /> : null}
      {data.data && data.data.notes.length === 0 && !writing ? <Empty icon="mail-outline" title="Nothing yet" text="Tap Write to start." /> : null}
      {data.data?.notes.map((n) => (
        <Pressable key={n.id} onPress={() => openNote(n)}>
          <Card>
            <Row>
              <Ionicons name={!n.from_me && !n.opened ? 'mail-unread' : 'mail-open-outline'} size={22} color={c.primary} />
              <View style={{ flex: 1 }}>
                <T raw weight="700" numberOfLines={1}>{n.title || n.body.slice(0, 40)}</T>
                <Muted>
                  {n.from_me ? 'You' : 'Partner'} · {timeAgo(n.created_at)}
                  {n.from_me && n.deliver_at && new Date(n.deliver_at) > new Date() ? ` · delivers ${fmtDate(n.deliver_at, true)}` : ''}
                  {n.from_me && n.opened ? ' · opened' : ''}
                </Muted>
              </View>
            </Row>
          </Card>
        </Pressable>
      ))}
    </Screen>
  );
}

function Compose({ kind, onDone, onCancel }: { kind: Kind; onDone: () => void; onCancel: () => void }) {
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('09:00');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const [dateErr, setDateErr] = useState<string | undefined>();

  const save = async () => {
    setDateErr(undefined);
    let deliver: string | undefined;
    if (kind === 'letter' && date.trim()) {
      const iso = toIso(date, time);
      if (!iso) return setDateErr('Use YYYY-MM-DD and HH:MM.');
      deliver = iso;
    }
    setBusy(true);
    setErr(null);
    try {
      await api('POST', '/notes', { kind, title: title.trim() || undefined, body: body.trim(), deliver_at: deliver });
      onDone();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <Field label="Title (optional)" value={title} onChangeText={setTitle} maxLength={150} placeholder={kind === 'letter' ? 'Open when you miss me' : ''} />
      <Field label="Message" value={body} onChangeText={setBody} multiline error={f.body} maxLength={20000} style={{ minHeight: 140, textAlignVertical: 'top' }} />
      {kind === 'letter' ? (
        <Row>
          <View style={{ flex: 1 }}>
            <Field label="Deliver on (optional, YYYY-MM-DD)" value={date} onChangeText={setDate} error={dateErr ?? f.deliver_at} keyboardType="numbers-and-punctuation" />
          </View>
          <View style={{ width: 100 }}>
            <Field label="Time" value={time} onChangeText={setTime} keyboardType="numbers-and-punctuation" />
          </View>
        </Row>
      ) : null}
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Row>
        <Button title={kind === 'letter' && date ? 'Seal & schedule' : 'Save'} style={{ flex: 1 }} onPress={save} loading={busy} disabled={!body.trim()} />
        <Button title="Cancel" variant="ghost" onPress={onCancel} />
      </Row>
    </Card>
  );
}
