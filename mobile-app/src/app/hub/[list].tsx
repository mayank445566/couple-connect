import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { Button, Card, Empty, ErrorBox, Field, Header, Muted, Pill, Row, Screen, SkeletonList, T, errorText, fieldErrors } from '../../components/ui';
import { api, authedSource, uploadMedia } from '../../lib/api';
import { daysUntil, HUB_LISTS, HubList, localDate } from '../../lib/constants';
import { fmtDate, toIso } from '../../lib/dates';
import { useLoad } from '../../lib/useLoad';
import { useSyncEvent } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

type Item = Record<string, any> & { id: number; title: string };

const CAL_KINDS = ['date', 'call', 'trip', 'birthday', 'anniversary', 'reminder', 'other'];

export default function HubListScreen() {
  const { list } = useLocalSearchParams<{ list: HubList }>();
  const cfg = HUB_LISTS[list] ?? HUB_LISTS.countdowns;
  const { c } = useTheme();
  const items = useLoad(() => api<Item[]>('GET', `/hub/${list}`), [list]);
  const [adding, setAdding] = useState(false);
  useSyncEvent('hub.', () => items.reload());

  const patch = async (it: Item, body: Record<string, unknown>) => {
    try {
      await api('PATCH', `/hub/${list}/${it.id}`, body);
      items.reload();
    } catch (e) {
      Alert.alert('Could not update', errorText(e));
    }
  };
  const del = (it: Item) =>
    Alert.alert('Delete?', `"${it.title}" will be removed for both of you.`, [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Delete', style: 'destructive', onPress: async () => { try { await api('DELETE', `/hub/${list}/${it.id}`); items.reload(); } catch (e) { Alert.alert('Could not delete', errorText(e)); } } },
    ]);

  return (
    <Screen refreshing={items.refreshing} onRefresh={items.onRefresh}>
      <Header title={cfg.title} onBack={() => router.back()} right={!adding ? <Button title="Add" icon="add" variant="ghost" onPress={() => setAdding(true)} /> : null} />
      {adding ? <AddForm list={list} onDone={() => { setAdding(false); items.reload(); }} onCancel={() => setAdding(false)} /> : null}
      {items.error ? <ErrorBox error={items.error} onRetry={items.reload} /> : null}
      {!items.data && !items.error ? <SkeletonList /> : null}
      {items.data && items.data.length === 0 && !adding ? <Empty icon={cfg.icon} title="Nothing here yet" text={cfg.empty} /> : null}
      {items.data?.map((it) => (
        <Card key={it.id}>
          <Row>
            {list === 'bucket' ? (
              <Pressable onPress={() => patch(it, { done: !it.done })} hitSlop={8} accessibilityRole="checkbox" accessibilityState={{ checked: !!it.done }}>
                <Ionicons name={it.done ? 'checkmark-circle' : 'ellipse-outline'} size={26} color={it.done ? c.success : c.muted} />
              </Pressable>
            ) : null}
            {list === 'countdowns' ? <T size={26}>{it.emoji || '⏳'}</T> : null}
            <View style={{ flex: 1 }}>
              <T weight="700" style={it.done ? { textDecorationLine: 'line-through', opacity: 0.6 } : undefined}>{it.title}</T>
              {list === 'countdowns' ? <Muted>{daysUntil(it.target_at) > 0 ? `${daysUntil(it.target_at)} days to go · ${fmtDate(it.target_at)}` : `Reached on ${fmtDate(it.target_at)}`}</Muted> : null}
              {list === 'calendar' ? <Muted>{it.kind} · {fmtDate(it.starts_at, !it.all_day)}{it.repeat_yearly ? ' · every year' : ''}</Muted> : null}
              {list === 'memories' ? <Muted>{it.happened_on}{it.is_milestone ? ' · milestone' : ''}</Muted> : null}
              {it.notes ? <Muted>{it.notes}</Muted> : null}
              {it.body ? <T size={14}>{it.body}</T> : null}
            </View>
            {list === 'memories' ? (
              <Pressable onPress={() => patch(it, { is_favorite: !it.is_favorite })} hitSlop={8} accessibilityLabel="Favorite">
                <Ionicons name={it.is_favorite ? 'heart' : 'heart-outline'} size={22} color={c.primary} />
              </Pressable>
            ) : null}
            <Pressable onPress={() => del(it)} hitSlop={8} accessibilityLabel="Delete">
              <Ionicons name="trash-outline" size={20} color={c.muted} />
            </Pressable>
          </Row>
          {it.media_url ? <Image source={authedSource(it.media_url)} style={{ width: '100%', height: 220, borderRadius: 14 }} contentFit="cover" /> : null}
        </Card>
      ))}
    </Screen>
  );
}

function AddForm({ list, onDone, onCancel }: { list: HubList; onDone: () => void; onCancel: () => void }) {
  const [v, setV] = useState<Record<string, string>>({ title: '', date: list === 'memories' ? localDate() : '', time: '', emoji: '', notes: '', kind: 'date' });
  const [repeat, setRepeat] = useState(false);
  const [milestone, setMilestone] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string; name: string; type: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const set = (k: string) => (t: string) => setV((s) => ({ ...s, [k]: t }));

  const pick = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    if (!r.canceled && r.assets[0]) setPhoto({ uri: r.assets[0].uri, name: r.assets[0].fileName ?? 'memory.jpg', type: r.assets[0].mimeType ?? 'image/jpeg' });
  };

  const save = async () => {
    setBusy(true);
    setErr(null);
    try {
      const body: Record<string, unknown> = { title: v.title.trim() };
      if (list === 'countdowns') {
        const iso = toIso(v.date, v.time);
        if (!iso) throw new Error('date');
        Object.assign(body, { target_at: iso, emoji: v.emoji || undefined });
      } else if (list === 'calendar') {
        const iso = toIso(v.date, v.time);
        if (!iso) throw new Error('date');
        Object.assign(body, { starts_at: iso, kind: v.kind, all_day: !v.time, repeat_yearly: repeat, notes: v.notes || undefined });
      } else if (list === 'bucket') {
        Object.assign(body, { notes: v.notes || undefined });
      } else if (list === 'memories') {
        if (!toIso(v.date)) throw new Error('date');
        const media = photo ? await uploadMedia(photo, 'image') : null;
        Object.assign(body, { happened_on: v.date.trim(), body: v.notes || undefined, media_id: media?.id, is_milestone: milestone });
      }
      await api('POST', `/hub/${list}`, body);
      onDone();
    } catch (e) {
      if (e instanceof Error && e.message === 'date') setErr({ fields: { date: 'Use YYYY-MM-DD (time as HH:MM).' } });
      else setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const dateErr = (err as any)?.fields?.date ?? f.target_at ?? f.starts_at ?? f.happened_on;
  return (
    <Card>
      <Field label="Title" value={v.title} onChangeText={set('title')} error={f.title} maxLength={150} autoFocus />
      {list !== 'bucket' ? (
        <Row>
          <View style={{ flex: 1 }}>
            <Field label="Date (YYYY-MM-DD)" value={v.date} onChangeText={set('date')} error={dateErr} placeholder={localDate()} keyboardType="numbers-and-punctuation" />
          </View>
          {list !== 'memories' ? (
            <View style={{ width: 110 }}>
              <Field label="Time" value={v.time} onChangeText={set('time')} placeholder="18:30" keyboardType="numbers-and-punctuation" />
            </View>
          ) : null}
        </Row>
      ) : null}
      {list === 'countdowns' ? <Field label="Emoji" value={v.emoji} onChangeText={set('emoji')} placeholder="✈️" maxLength={4} /> : null}
      {list === 'calendar' ? (
        <>
          <Row style={{ flexWrap: 'wrap' }} gap={6}>
            {CAL_KINDS.map((k) => (
              <Pill key={k} label={k} active={v.kind === k} onPress={() => set('kind')(k)} />
            ))}
          </Row>
          <Pill label="Repeats every year" icon={repeat ? 'checkmark' : undefined} active={repeat} onPress={() => setRepeat(!repeat)} />
        </>
      ) : null}
      {list !== 'countdowns' ? <Field label={list === 'memories' ? 'Story' : 'Notes'} value={v.notes} onChangeText={set('notes')} multiline maxLength={list === 'memories' ? 2000 : 1000} /> : null}
      {list === 'memories' ? (
        <>
          {photo ? <Image source={{ uri: photo.uri }} style={{ width: '100%', height: 180, borderRadius: 14 }} /> : null}
          <Row>
            <Button title={photo ? 'Change photo' : 'Add photo'} variant="secondary" icon="image-outline" onPress={pick} />
            <Pill label="Milestone" icon={milestone ? 'checkmark' : undefined} active={milestone} onPress={() => setMilestone(!milestone)} />
          </Row>
        </>
      ) : null}
      {err && !(err as any).fields ? <ErrorBox error={err} /> : null}
      <Row>
        <Button title="Save" style={{ flex: 1 }} onPress={save} loading={busy} disabled={!v.title.trim()} />
        <Button title="Cancel" variant="ghost" onPress={onCancel} />
      </Row>
    </Card>
  );
}
