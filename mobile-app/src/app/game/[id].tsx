import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Hero, Muted, Row, Screen, SkeletonList, T, errorText } from '../../components/ui';
import { api, Couple } from '../../lib/api';
import { PromptCard } from '../../components/PromptCard';
import { Game, LEVEL_NAMES, Session } from '../../lib/games';
import { t } from '../../lib/i18n';
import { play } from '../../lib/sounds';
import { useLoad } from '../../lib/useLoad';
import { useAuth } from '../../state/AuthProvider';
import { useSyncEvent } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

/** Shows the option in the app language even though answers are stored in English. */
function labelOf(choice: string, q?: { options: string[] | null; labels: string[] | null }): string {
  const i = q?.options?.indexOf(choice) ?? -1;
  return i >= 0 && q?.labels?.[i] ? q.labels[i] : choice;
}

function describe(a: any, names: Record<number, string>, q?: { options: string[] | null; labels: string[] | null }): string {
  if (!a) return '—';
  if (a.choice) return labelOf(a.choice, q);
  if (a.user_id) return names[a.user_id] ?? t('Someone');
  if (a.text) return a.text;
  if (a.done) return t('Done ✓');
  if (a.statements) return a.statements.map((s: string, i: number) => `${i + 1}. ${s}${a.lie === i ? `  (${t('the lie')})` : ''}`).join('\n');
  if (typeof a.pick === 'number') return t('Statement {n}', { n: a.pick + 1 });
  return '—';
}

const LOVE_LANG: Record<string, string> = { words: 'Words of affirmation', time: 'Quality time', gifts: 'Gifts', service: 'Acts of service', touch: 'Physical touch' };

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
  const roundStatus = s.data?.round?.status;
  const result = s.data?.round?.result;
  // Sounds: a card flip for each new card, a little fanfare when you match or guess right
  useEffect(() => {
    if (!roundId) return;
    play(s.data?.game.wild ? 'sparkle' : 'flip');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roundId]);
  useEffect(() => {
    if (roundStatus === 'revealed' && (result?.match === true || result?.correct === true)) play('match');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roundStatus, result?.match, result?.correct]);
  useEffect(() => {
    if (s.data?.status === 'finished') play('match');
  }, [s.data?.status]);
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
  const partnerName = partner?.display_name ?? t('your partner');
  const names: Record<number, string> = { [myId]: t('You'), ...(partner ? { [partner.id]: partner.display_name } : {}) };
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
          <T color="#fff" weight="700" size={18} center>{mine ? t('Waiting for {name} to join', { name: partnerName }) : t('{name} wants to play', { name: partner?.display_name ?? t('Your partner') })}</T>
          <T color="#ffffffCC" center>{d.endless ? t('Endless') : t('{n} rounds', { n: d.total_rounds })}{d.level ? ` · ${t(LEVEL_NAMES[d.level] ?? d.level)}` : ''}</T>
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
          {d.summary?.compatibility_percent != null ? <T color="#fff" size={34} weight="800">{t('{n}% in sync', { n: d.summary.compatibility_percent })}</T> : null}
          {d.game.mode === 'quiz' || d.game.mode === 'guess' ? <T raw color="#fff">{t('You')} {myScore} · {partner?.display_name ?? t('Partner')} {partnerScore}</T> : null}
          {d.summary ? <T color="#ffffffCC">{t('{a} played · {b} skipped', { a: d.summary.rounds_played, b: d.summary.rounds_skipped })}</T> : null}
          {d.summary?.top_category?.[String(myId)] ? <T color="#fff">{t('Your top: {c}', { c: t(LOVE_LANG[d.summary.top_category[String(myId)]] ?? d.summary.top_category[String(myId)]) })}</T> : null}
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
        <Muted>{d.endless ? t('Round {n} · Endless', { n: d.round_no }) : t('Round {n} of {total}', { n: d.round_no, total: d.total_rounds })}</Muted>
        {d.game.mode === 'match' ? <Muted>{t('In sync: {n}', { n: d.scores?.sync ?? 0 })}</Muted> : d.game.mode !== 'prompt' ? <Muted raw>{t('You')} {myScore} · {partner?.display_name ?? t('Partner')} {partnerScore}</Muted> : null}
      </Row>

      {r && (d.game.section === 'adult' || d.game.wild) ? (
        <PromptCard
          cardKey={r.id}
          text={r.question.text}
          kind={r.question.kind}
          level={r.question.level ? t(LEVEL_NAMES[r.question.level] ?? r.question.level) : null}
          wild={!!d.game.wild}
          caption={r.subject_id != null ? (isSubject ? t('Your turn') : t("{name}'s turn", { name: partnerName })) : null}
        />
      ) : r ? (
        <Hero>
          {r.subject_id != null ? <T color="#ffffffCC" size={13}>{isSubject ? t('About you') : t('About {name}', { name: partnerName })}</T> : null}
          {r.question.kind === 'truth' || r.question.kind === 'dare' ? <T color="#fff" weight="800" size={13}>{r.question.kind === 'truth' ? 'TRUTH' : 'DARE'}</T> : null}
          <T raw color="#fff" size={21} weight="700">{r.question.text}</T>
        </Hero>
      ) : null}

      {r && r.status === 'open' ? (
        r.my_answer ? (
          <Card>
            <Muted>Your answer</Muted>
            <T raw>{describe(r.my_answer, names, r.question)}</T>
            <Muted>{r.partner_answered ? t('Revealing…') : t('Waiting for {name}…', { name: partnerName })}</Muted>
          </Card>
        ) : promptOnlySubject && !isSubject ? (
          <Card>
            <T>{t("It's {name}'s turn. You'll see their answer here.", { name: partnerName })}</T>
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
              <T>{t('Waiting for {name} to write their statements…', { name: partnerName })}</T>
            </Card>
          )
        ) : twoTruths ? (
          <View style={{ gap: 8 }}>
            <Muted>Write two truths and one lie, then tap the lie.</Muted>
            {statements.map((st, i) => (
              <Row key={i}>
                <View style={{ flex: 1 }}>
                  <Field value={st} onChangeText={(t) => setStatements((a) => a.map((x, j) => (j === i ? t : x)))} placeholder={t('Statement {n}', { n: i + 1 })} maxLength={200} />
                </View>
                <Pressable onPress={() => setLie(i)} accessibilityLabel={t('Mark statement {n} as the lie', { n: i + 1 })}>
                  <Ionicons name={lie === i ? 'close-circle' : 'ellipse-outline'} size={28} color={lie === i ? c.danger : c.muted} />
                </Pressable>
              </Row>
            ))}
            <Button title="Send" disabled={lie === null || statements.some((x) => !x.trim())} loading={busy} onPress={() => call('answer', { answer: { statements, lie } })} />
          </View>
        ) : r.question.pick_partner ? (
          <Row>
            <Option label={t('Me')} onPress={() => call('answer', { answer: { user_id: myId } })} disabled={busy} flex />
            {partner ? <Option label={partner.display_name} onPress={() => call('answer', { answer: { user_id: partner.id } })} disabled={busy} flex /> : null}
          </Row>
        ) : r.question.options?.length ? (
          <View style={{ gap: 8 }}>
            {isGuesser ? <Muted>{t('Guess what {name} picks', { name: partnerName })}</Muted> : null}
            {r.question.options.map((o, i) => (
              <Option key={o} label={r.question.labels?.[i] ?? o} onPress={() => call('answer', { answer: { choice: o } })} disabled={busy} />
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
                <T raw>{describe(r.my_answer, names, r.question)}</T>
              </>
            ) : null}
            {r.partner_answer ? (
              <>
                <Muted raw>{partner?.display_name ?? t('Partner')}</Muted>
                <T raw>{describe(r.partner_answer, names, r.question)}</T>
              </>
            ) : null}
          </Card>
          {r.result?.match != null ? <T center size={18} weight="700">{r.result.match ? '💞 Same answer!' : 'Different answers'}</T> : null}
          {r.result?.correct_answer ? <T center>{t('Correct answer: {a}', { a: labelOf(r.result.correct_answer, r.question) })}</T> : null}
          {d.game.mode === 'guess' && typeof r.result?.correct === 'boolean' ? <T center size={18} weight="700">{r.result.correct ? '🎯 Guessed right!' : 'Not quite!'}</T> : null}
          {r.result?.needs_judge ? (
            isSubject ? (
              <View style={{ gap: 8 }}>
                <T center>{t("Was {name}'s guess right?", { name: partnerName })}</T>
                <Row>
                  <Button title="Yes" style={{ flex: 1 }} onPress={() => call('judge', { correct: true })} />
                  <Button title="No" variant="secondary" style={{ flex: 1 }} onPress={() => call('judge', { correct: false })} />
                </Row>
              </View>
            ) : (
              <Muted center>{t('{name} decides if your guess was right…', { name: partner?.display_name ?? t('Your partner') })}</Muted>
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
          <Button title={!d.endless && d.round_no >= d.total_rounds ? 'See results' : d.game.wild ? 'Draw next card' : 'Next question'} onPress={() => call('next')} loading={busy} />
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
      <T raw weight="600" center>{label}</T>
    </Pressable>
  );
}
