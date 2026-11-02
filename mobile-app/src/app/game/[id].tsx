import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Hero, Muted, Row, Screen, SkeletonList, T, errorText } from '../../components/ui';
import { api, Couple } from '../../lib/api';
import { Game, Session } from '../../lib/games';
import { useLoad } from '../../lib/useLoad';
import { useAuth } from '../../state/AuthProvider';
import { useSyncEvent } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

function describe(a: any, names: Record<number, string>): string {
  if (!a) return '—';
  if (a.choice) return a.choice;
  if (a.user_id) return names[a.user_id] ?? 'Someone';
  if (a.text) return a.text;
  if (a.done) return 'Done ✓';
  if (a.statements) return a.statements.map((s: string, i: number) => `${i + 1}. ${s}${a.lie === i ? '  (the lie)' : ''}`).join('\n');
  if (typeof a.pick === 'number') return `Statement ${a.pick + 1}`;
  return '—';
}

export default function GameSession() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { me } = useAuth();
  const { c } = useTheme();
  const s = useLoad(() => api<Session>('GET', `/games/sessions/${id}`), [id]);
  const couple = useLoad(() => api<Couple>('GET', '/couple'));
  const [game, setGame] = useState<Game | null>(null);
  const [text, setText] = useState('');
  const [statements, setStatements] = useState(['', '', '']);
  const [lie, setLie] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useSyncEvent('game.', (e) => {
    if (!e.payload?.session_id || String(e.payload.session_id) === String(id)) s.reload();
  });

  // Poll quickly while a game is live so turns feel instant (the relay in Phase 4 replaces this)
  const live = s.data?.status === 'active' || s.data?.status === 'invited';
  useEffect(() => {
    if (!live) return;
    const t = setInterval(() => s.reload(), 3000);
    return () => clearInterval(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [live]);

  const slug = s.data?.game.slug;
  useEffect(() => {
    if (slug) api<Game>('GET', `/games/${slug}`).then(setGame).catch(() => {});
  }, [slug]);

  const roundId = s.data?.round?.id;
  useEffect(() => {
    setText('');
    setStatements(['', '', '']);
    setLie(null);
    setErr(null);
  }, [roundId]);

  const call = async (path: string, body?: unknown) => {
    setBusy(true);
    setErr(null);
    try {
      s.setData(await api<Session>('POST', `/games/sessions/${id}/${path}`, body));
    } catch (e) {
      setErr(errorText(e));
      s.reload();
    } finally {
      setBusy(false);
    }
  };

  const d = s.data;
  if (!d) return <Screen>{s.error ? <ErrorBox error={s.error} onRetry={s.reload} /> : <SkeletonList />}</Screen>;

  const myId = me!.id;
  const partner = couple.data?.partner;
  const names: Record<number, string> = { [myId]: 'You', ...(partner ? { [partner.id]: partner.display_name } : {}) };
  const r = d.round;
  const isSubject = r?.subject_id === myId;
  const isGuesser = d.game.mode === 'guess' && r?.subject_id != null && !isSubject;
  const promptOnlySubject = d.game.mode === 'prompt' && game && !game.both_answer && r?.subject_id != null;
  const twoTruths = r?.question.kind === 'two_truths';
  const myScore = d.scores?.[String(myId)] ?? 0;
  const partnerScore = partner ? d.scores?.[String(partner.id)] ?? 0 : 0;

  const stopBtn = d.can_stop ? <Button title="Stop" variant="ghost" icon="stop-circle-outline" onPress={() => call('stop')} /> : null;

  // ---- invited ----
  if (d.status === 'invited') {
    const mine = d.started_by === myId;
    return (
      <Screen>
        <Header title={d.game.title} onBack={() => router.back()} />
        <Hero style={{ alignItems: 'center' }}>
          <Ionicons name="game-controller" size={42} color="#fff" />
          <T color="#fff" weight="700" size={18} center>{mine ? `Waiting for ${partner?.display_name ?? 'your partner'} to join` : `${partner?.display_name ?? 'Your partner'} wants to play`}</T>
          <T color="#ffffffCC" center>{d.total_rounds} rounds{d.level ? ` · ${d.level}` : ''}</T>
        </Hero>
        {d.game.section === 'adult' ? <Muted center>18+ game. Either of you can skip any question or stop at any time.</Muted> : null}
        {mine ? (
          <Button title="Cancel invite" variant="secondary" onPress={() => call('stop')} loading={busy} />
        ) : (
          <Row>
            <Button title="Join" style={{ flex: 1 }} onPress={() => call('join')} loading={busy} />
            <Button title="Not now" variant="secondary" style={{ flex: 1 }} onPress={() => call('decline')} />
          </Row>
        )}
        {err ? <Muted center>{err}</Muted> : null}
      </Screen>
    );
  }

  // ---- ended ----
  if (d.status !== 'active') {
    return (
      <Screen>
        <Header title={d.game.title} onBack={() => router.back()} />
        <Hero style={{ alignItems: 'center' }}>
          <T size={40}>{d.status === 'finished' ? '🎉' : '👋'}</T>
          <T color="#fff" size={20} weight="700">{d.status === 'finished' ? 'Game complete' : d.status === 'declined' ? 'Invite declined' : 'Game stopped'}</T>
          {d.summary?.compatibility_percent != null ? <T color="#fff" size={34} weight="800">{d.summary.compatibility_percent}% in sync</T> : null}
          {d.game.mode === 'quiz' || d.game.mode === 'guess' ? <T color="#fff">You {myScore} · {partner?.display_name ?? 'Partner'} {partnerScore}</T> : null}
          {d.summary ? <T color="#ffffffCC">{d.summary.rounds_played} played · {d.summary.rounds_skipped} skipped</T> : null}
          {d.summary?.top_category?.[String(myId)] ? <T color="#fff">Your top: {d.summary.top_category[String(myId)].replace(/_/g, ' ')}</T> : null}
        </Hero>
        <Button title="Play again" onPress={() => router.replace(`/games/${d.game.slug}`)} />
        <Button title="Back to games" variant="ghost" onPress={() => router.back()} />
      </Screen>
    );
  }

  // ---- active ----
  return (
    <Screen refreshing={s.refreshing} onRefresh={s.onRefresh}>
      <Header title={d.game.title} onBack={() => router.back()} right={stopBtn} />
      <Row style={{ justifyContent: 'space-between' }}>
        <Muted>Round {d.round_no} of {d.total_rounds}</Muted>
        {d.game.mode === 'match' ? <Muted>In sync: {d.scores?.sync ?? 0}</Muted> : d.game.mode !== 'prompt' ? <Muted>You {myScore} · {partner?.display_name ?? 'Partner'} {partnerScore}</Muted> : null}
      </Row>

      {r ? (
        <Hero>
          {r.subject_id != null ? <T color="#ffffffCC" size={13}>{isSubject ? 'About you' : `About ${partner?.display_name ?? 'your partner'}`}</T> : null}
          {r.question.kind === 'truth' || r.question.kind === 'dare' ? <T color="#fff" weight="800" size={13}>{r.question.kind.toUpperCase()}</T> : null}
          <T color="#fff" size={21} weight="700">{r.question.text}</T>
        </Hero>
      ) : null}

      {r && r.status === 'open' ? (
        r.my_answer ? (
          <Card>
            <Muted>Your answer</Muted>
            <T>{describe(r.my_answer, names)}</T>
            <Muted>{r.partner_answered ? 'Revealing…' : `Waiting for ${partner?.display_name ?? 'your partner'}…`}</Muted>
          </Card>
        ) : promptOnlySubject && !isSubject ? (
          <Card>
            <T>It's {partner?.display_name ?? 'your partner'}'s turn. You'll see their answer here.</T>
          </Card>
        ) : twoTruths && isGuesser ? (
          r.partner_answer?.statements ? (
            <View style={{ gap: 8 }}>
              <T weight="600">Which one is the lie?</T>
              {r.partner_answer.statements.map((st: string, i: number) => (
                <Option key={i} label={st} onPress={() => call('answer', { answer: { pick: i } })} disabled={busy} />
              ))}
            </View>
          ) : (
            <Card>
              <T>Waiting for {partner?.display_name ?? 'your partner'} to write their statements…</T>
            </Card>
          )
        ) : twoTruths ? (
          <View style={{ gap: 8 }}>
            <Muted>Write two truths and one lie, then tap the lie.</Muted>
            {statements.map((st, i) => (
              <Row key={i}>
                <View style={{ flex: 1 }}>
                  <Field value={st} onChangeText={(t) => setStatements((a) => a.map((x, j) => (j === i ? t : x)))} placeholder={`Statement ${i + 1}`} maxLength={200} />
                </View>
                <Pressable onPress={() => setLie(i)} accessibilityLabel={`Mark statement ${i + 1} as the lie`}>
                  <Ionicons name={lie === i ? 'close-circle' : 'ellipse-outline'} size={28} color={lie === i ? c.danger : c.muted} />
                </Pressable>
              </Row>
            ))}
            <Button title="Send" disabled={lie === null || statements.some((x) => !x.trim())} loading={busy} onPress={() => call('answer', { answer: { statements, lie } })} />
          </View>
        ) : r.question.pick_partner ? (
          <Row>
            <Option label="Me" onPress={() => call('answer', { answer: { user_id: myId } })} disabled={busy} flex />
            {partner ? <Option label={partner.display_name} onPress={() => call('answer', { answer: { user_id: partner.id } })} disabled={busy} flex /> : null}
          </Row>
        ) : r.question.options?.length ? (
          <View style={{ gap: 8 }}>
            {isGuesser ? <Muted>Guess what {partner?.display_name ?? 'your partner'} picks</Muted> : null}
            {r.question.options.map((o) => (
              <Option key={o} label={o} onPress={() => call('answer', { answer: { choice: o } })} disabled={busy} />
            ))}
          </View>
        ) : r.question.kind === 'dare' || r.question.kind === 'challenge' ? (
          <Button title="Done ✓" onPress={() => call('answer', { answer: { done: true } })} loading={busy} />
        ) : (
          <View style={{ gap: 8 }}>
            <Field value={text} onChangeText={setText} placeholder={isGuesser ? 'Your guess' : 'Your answer'} multiline maxLength={1000} style={{ minHeight: 80, textAlignVertical: 'top' }} />
            <Button title="Send" disabled={!text.trim()} loading={busy} onPress={() => call('answer', { answer: { text: text.trim() } })} />
          </View>
        )
      ) : null}

      {r && r.status === 'revealed' ? (
        <View style={{ gap: 10 }}>
          <Card>
            {r.my_answer ? (
              <>
                <Muted>You</Muted>
                <T>{describe(r.my_answer, names)}</T>
              </>
            ) : null}
            {r.partner_answer ? (
              <>
                <Muted>{partner?.display_name ?? 'Partner'}</Muted>
                <T>{describe(r.partner_answer, names)}</T>
              </>
            ) : null}
          </Card>
          {r.result?.match != null ? <T center size={18} weight="700">{r.result.match ? '💞 Same answer!' : 'Different answers'}</T> : null}
          {r.result?.correct_answer ? <T center>Correct answer: {r.result.correct_answer}</T> : null}
          {d.game.mode === 'guess' && typeof r.result?.correct === 'boolean' ? <T center size={18} weight="700">{r.result.correct ? '🎯 Guessed right!' : 'Not quite!'}</T> : null}
          {r.result?.needs_judge ? (
            isSubject ? (
              <View style={{ gap: 8 }}>
                <T center>Was {partner?.display_name ?? 'your partner'}'s guess right?</T>
                <Row>
                  <Button title="Yes" style={{ flex: 1 }} onPress={() => call('judge', { correct: true })} />
                  <Button title="No" variant="secondary" style={{ flex: 1 }} onPress={() => call('judge', { correct: false })} />
                </Row>
              </View>
            ) : (
              <Muted center>{partner?.display_name ?? 'Your partner'} decides if your guess was right…</Muted>
            )
          ) : null}
        </View>
      ) : null}

      {r && r.status === 'skipped' ? <Muted center>Skipped</Muted> : null}

      {r && (r.status !== 'open' || !r.my_answer) && !r.result?.needs_judge ? (
        r.status === 'open' ? (
          d.can_skip ? <Button title="Skip this one" variant="ghost" onPress={() => call('skip')} /> : null
        ) : game?.choose_kind ? (
          <Row>
            <Button title="Next: Truth" style={{ flex: 1 }} onPress={() => call('next', { kind: 'truth' })} loading={busy} />
            <Button title="Next: Dare" style={{ flex: 1 }} onPress={() => call('next', { kind: 'dare' })} />
          </Row>
        ) : (
          <Button title={d.round_no >= d.total_rounds ? 'See results' : 'Next question'} onPress={() => call('next')} loading={busy} />
        )
      ) : null}
      {r && r.status === 'open' && r.my_answer && d.can_skip ? <Button title="Skip this one" variant="ghost" onPress={() => call('skip')} /> : null}
      {err ? <Muted center>{err}</Muted> : null}
    </Screen>
  );
}

function Option({ label, onPress, disabled, flex }: { label: string; onPress: () => void; disabled?: boolean; flex?: boolean }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={onPress} disabled={disabled} style={({ pressed }) => ({ flex: flex ? 1 : undefined, padding: 16, borderRadius: 18, backgroundColor: pressed ? c.primary + '22' : c.surface, borderWidth: 1, borderColor: c.border, opacity: disabled ? 0.6 : 1 })}>
      <T weight="600" center>{label}</T>
    </Pressable>
  );
}
