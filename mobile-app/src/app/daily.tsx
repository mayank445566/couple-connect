import { router } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Button, Card, ErrorBox, Field, Header, Hero, Muted, Screen, SkeletonList, T } from '../components/ui';
import { api } from '../lib/api';
import { useLoad } from '../lib/useLoad';
import { useSyncEvent } from '../state/SyncProvider';

type Daily = { day: string; question: { id: number; text: string } | null; my_answer: string | null; partner_answered: boolean; partner_answer: string | null; streak: number };
type Past = { day: string; question: string; answers: { mine?: string; partner?: string } };

export default function DailyQuestion() {
  const d = useLoad(() => api<Daily>('GET', '/daily'));
  const hist = useLoad(() => api<Past[]>('GET', '/daily/history').catch(() => [] as Past[]));
  const [text, setText] = useState('');
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  useSyncEvent('daily.', () => d.reload());

  useEffect(() => {
    if (d.data?.my_answer) setText(d.data.my_answer);
  }, [d.data?.my_answer]);

  const save = async () => {
    setBusy(true);
    setErr(null);
    try {
      d.setData(await api<Daily>('POST', '/daily', { answer: text.trim() }));
      setEditing(false);
      hist.reload();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const q = d.data;
  return (
    <Screen refreshing={d.refreshing} onRefresh={d.onRefresh}>
      <Header title="Daily question" onBack={() => router.back()} />
      {!q ? d.error ? <ErrorBox error={d.error} onRetry={d.reload} /> : <SkeletonList rows={2} /> : (
        <>
          <Hero>
            <T color="#ffffffCC" size={13}>{q.day}{q.streak > 0 ? ` · 🔥 ${q.streak}-day streak` : ''}</T>
            <T color="#fff" size={21} weight="700">{q.question?.text ?? 'No question today.'}</T>
          </Hero>
          {q.question && (!q.my_answer || editing) ? (
            <>
              <Field value={text} onChangeText={setText} multiline placeholder="Your answer" maxLength={1000} style={{ minHeight: 90, textAlignVertical: 'top' }} />
              <Button title={q.my_answer ? 'Save' : 'Answer'} onPress={save} disabled={!text.trim()} loading={busy} />
              <Muted center>You'll see your partner's answer once you've given yours.</Muted>
            </>
          ) : null}
          {q.my_answer && !editing ? (
            <Card>
              <Muted>You</Muted>
              <T>{q.my_answer}</T>
              <Button title="Edit" variant="ghost" onPress={() => setEditing(true)} />
            </Card>
          ) : null}
          {q.my_answer ? (
            <Card>
              <Muted>Partner</Muted>
              <T>{q.partner_answer ?? 'Hasn’t answered yet.'}</T>
            </Card>
          ) : q.partner_answered ? (
            <Muted center>Your partner already answered. Answer to see theirs.</Muted>
          ) : null}
          {err ? <ErrorBox error={err} /> : null}
          {hist.data?.length ? <T weight="700">Past answers</T> : null}
          {hist.data?.map((h) => (
            <Card key={h.day}>
              <Muted>{h.day}</Muted>
              <T weight="600">{h.question}</T>
              <T>You: {h.answers.mine}</T>
              <T>Partner: {h.answers.partner ?? '—'}</T>
            </Card>
          ))}
        </>
      )}
    </Screen>
  );
}
