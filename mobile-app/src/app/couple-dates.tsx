import { router } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Muted, Pill, Row, Screen, SkeletonList, T } from '../components/ui';
import { api, Couple } from '../lib/api';
import { splitLocal, toIso } from '../lib/dates';
import { useLoad } from '../lib/useLoad';

const DISAPPEAR = [
  [0, 'Off'],
  [3600, '1 hour'],
  [86400, '24 hours'],
  [604800, '7 days'],
] as const;

/** Dates and chat settings shared by the couple: both partners see and can change them. */
export default function CoupleDates() {
  const cpl = useLoad(() => api<Couple>('GET', '/couple'));
  const [v, setV] = useState({ start: '', anniv: '', meetDate: '', meetTime: '' });
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const [bad, setBad] = useState<string | null>(null);

  useEffect(() => {
    const d = cpl.data;
    if (!d) return;
    const m = splitLocal(d.next_meeting_at);
    setV({ start: d.relationship_start ?? '', anniv: d.anniversary_date ?? '', meetDate: m.date, meetTime: m.time });
  }, [cpl.data]);

  const save = async () => {
    setBad(null);
    const meeting = v.meetDate.trim() ? toIso(v.meetDate, v.meetTime) : null;
    if (v.meetDate.trim() && !meeting) return setBad('Next meeting: use YYYY-MM-DD and HH:MM.');
    for (const x of [v.start, v.anniv]) if (x.trim() && !toIso(x)) return setBad('Dates must be YYYY-MM-DD.');
    setBusy(true);
    setErr(null);
    try {
      await api('PATCH', '/couple', { relationship_start: v.start.trim() || null, anniversary_date: v.anniv.trim() || null, next_meeting_at: meeting });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
      cpl.reload();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const setDisappear = async (sec: number) => {
    try {
      await api('PATCH', '/couple', { disappearing_seconds: sec });
      cpl.reload();
    } catch (e) {
      setErr(e);
    }
  };

  if (!cpl.data) return <Screen>{cpl.error ? <ErrorBox error={cpl.error} onRetry={cpl.reload} /> : <SkeletonList />}</Screen>;

  return (
    <Screen>
      <Header title="Our dates" onBack={() => router.back()} />
      <Muted>These are shared. Your partner sees the same dates.</Muted>
      <Field label="Together since (YYYY-MM-DD)" value={v.start} onChangeText={(t) => setV({ ...v, start: t })} keyboardType="numbers-and-punctuation" />
      <Field label="Anniversary (YYYY-MM-DD)" value={v.anniv} onChangeText={(t) => setV({ ...v, anniv: t })} keyboardType="numbers-and-punctuation" />
      <Row>
        <View style={{ flex: 1 }}>
          <Field label="Next meeting (YYYY-MM-DD)" value={v.meetDate} onChangeText={(t) => setV({ ...v, meetDate: t })} keyboardType="numbers-and-punctuation" />
        </View>
        <View style={{ width: 100 }}>
          <Field label="Time" value={v.meetTime} onChangeText={(t) => setV({ ...v, meetTime: t })} placeholder="18:00" keyboardType="numbers-and-punctuation" />
        </View>
      </Row>
      {bad ? <Muted>{bad}</Muted> : null}
      {err ? <ErrorBox error={err} /> : null}
      <Button title={saved ? 'Saved ✓' : 'Save dates'} onPress={save} loading={busy} />

      <Card>
        <T weight="700">Disappearing messages</T>
        <Muted>New messages delete themselves for both of you after this time. Applies to both partners.</Muted>
        <Row style={{ flexWrap: 'wrap' }} gap={8}>
          {DISAPPEAR.map(([s, l]) => (
            <Pill key={s} label={l} active={cpl.data!.settings.disappearing_seconds === s} onPress={() => setDisappear(s)} />
          ))}
        </Row>
      </Card>
    </Screen>
  );
}
