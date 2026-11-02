import { router } from 'expo-router';
import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Muted, Row, Screen, T } from '../components/ui';
import { api } from '../lib/api';
import { moodOf, MOODS } from '../lib/constants';
import { useLoad } from '../lib/useLoad';
import { useTheme } from '../state/ThemeProvider';

type Entry = { mood: string; note: string | null };
type Day = { day: string; mine?: Entry; partner?: Entry };

export default function Mood() {
  const { c } = useTheme();
  const { data, reload } = useLoad(() => api<Day[]>('GET', '/moods'));
  const [pick, setPick] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const list = data ?? [];

  const save = async () => {
    if (!pick) return;
    setBusy(true);
    setErr(null);
    try {
      await api('POST', '/moods', { mood: pick, note: note.trim() || undefined });
      await reload();
      router.back();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <Header title="How are you feeling?" onBack={() => router.back()} />
      <Row style={{ flexWrap: 'wrap' }} gap={10}>
        {MOODS.map((m) => (
          <Pressable key={m.mood} onPress={() => setPick(m.mood)} style={{ width: '30%', flexGrow: 1, alignItems: 'center', padding: 12, borderRadius: 18, backgroundColor: pick === m.mood ? c.primary + '33' : c.surface, borderWidth: 1, borderColor: pick === m.mood ? c.primary : c.border }}>
            <T size={30}>{m.emoji}</T>
            <T size={12}>{m.label}</T>
          </Pressable>
        ))}
      </Row>
      <Field label="Add a note (optional)" value={note} onChangeText={setNote} maxLength={200} placeholder="Long day at work…" />
      {err ? <ErrorBox error={err} /> : null}
      <Button title="Share with partner" onPress={save} disabled={!pick} loading={busy} />
      {list.length ? (
        <View style={{ gap: 8 }}>
          <T weight="700">Recent</T>
          {list.slice(0, 20).map((d) => (
            <Card key={d.day} style={{ paddingVertical: 10 }}>
              <Muted>{d.day}</Muted>
              {(['mine', 'partner'] as const).map((who) =>
                d[who] ? (
                  <Row key={who}>
                    <T size={22}>{moodOf(d[who]!.mood)?.emoji ?? '🙂'}</T>
                    <View style={{ flex: 1 }}>
                      <T weight="600">{who === 'mine' ? 'You' : 'Partner'} · {moodOf(d[who]!.mood)?.label ?? d[who]!.mood}</T>
                      {d[who]!.note ? <Muted>{d[who]!.note}</Muted> : null}
                    </View>
                  </Row>
                ) : null,
              )}
            </Card>
          ))}
        </View>
      ) : null}
    </Screen>
  );
}
