import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Button, Card, ErrorBox, Field, Header, Muted, Pill, Row, Screen, SkeletonList, T, errorText } from '../../components/ui';
import { api, ApiError } from '../../lib/api';
import { gameIcon } from '../../lib/constants';
import { ADULT_LEVELS, FREE_LEVELS, Game, Session } from '../../lib/games';
import { useLoad } from '../../lib/useLoad';
import { useTheme } from '../../state/ThemeProvider';

type Custom = { id: number; kind: string; text: string; created_by: number };

export default function GameDetail() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const { c } = useTheme();
  const g = useLoad(() => api<Game>('GET', `/games/${slug}`), [slug]);
  const custom = useLoad(() => api<Custom[]>('GET', `/games/${slug}/custom-questions`).catch(() => [] as Custom[]), [slug]);
  const [level, setLevel] = useState<string | null>(null);
  const [pack, setPack] = useState<number | null>(null);
  const [rounds, setRounds] = useState<number | null>(null);
  const [newQ, setNewQ] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);

  const game = g.data;
  if (!game) return <Screen>{g.error ? <ErrorBox error={g.error} onRetry={g.reload} /> : <SkeletonList />}</Screen>;

  const levels = game.section === 'adult' ? ADULT_LEVELS : FREE_LEVELS;
  const packLevels = new Set(game.packs.map((p) => p.level).filter(Boolean));
  const shownLevels = levels.filter((l) => packLevels.has(l));

  const start = async () => {
    setBusy(true);
    setErr(null);
    try {
      const s = await api<Session>('POST', '/games/sessions', { game: game.slug, level: level ?? undefined, pack_id: pack ?? undefined, rounds: rounds ?? undefined });
      router.replace(`/game/${s.id}`);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'premium_required') router.push('/premium');
      else setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const addCustom = async () => {
    try {
      await api('POST', `/games/${game.slug}/custom-questions`, { text: newQ.trim() });
      setNewQ('');
      custom.reload();
    } catch (e) {
      setErr(e);
    }
  };

  return (
    <Screen>
      <Header title={game.title} onBack={() => router.back()} />
      <Card>
        <Row>
          <Ionicons name={gameIcon(game.icon)} size={28} color={c.primary} />
          <T style={{ flex: 1 }}>{game.description}</T>
        </Row>
        {game.section === 'adult' ? <Muted>Private and consensual. Either of you can skip any question or stop the game at any time.</Muted> : null}
      </Card>

      {shownLevels.length ? (
        <View style={{ gap: 6 }}>
          <T weight="700">Level</T>
          <Row style={{ flexWrap: 'wrap' }} gap={8}>
            <Pill label="Mixed" active={level === null} onPress={() => { setLevel(null); setPack(null); }} />
            {shownLevels.map((l) => (
              <Pill key={l} label={l[0].toUpperCase() + l.slice(1)} active={level === l} onPress={() => { setLevel(l); setPack(null); }} />
            ))}
          </Row>
        </View>
      ) : null}

      {game.packs.length > 1 ? (
        <View style={{ gap: 6 }}>
          <T weight="700">Question pack</T>
          {game.packs.map((p) => (
            <Pressable key={p.id} disabled={p.locked} onPress={() => setPack(pack === p.id ? null : p.id)}>
              <Card style={{ paddingVertical: 10, borderColor: pack === p.id ? c.primary : c.border, borderWidth: pack === p.id ? 2 : 1, opacity: p.locked ? 0.6 : 1 }}>
                <Row>
                  <T style={{ flex: 1 }}>{p.title}</T>
                  <Muted>{p.questions} questions</Muted>
                  {p.locked ? <Ionicons name="lock-closed" size={16} color={c.muted} /> : null}
                </Row>
              </Card>
            </Pressable>
          ))}
        </View>
      ) : null}

      <View style={{ gap: 6 }}>
        <T weight="700">Rounds</T>
        <Row gap={8}>
          {[5, 10, 15].map((n) => (
            <Pill key={n} label={String(n)} active={(rounds ?? game.rounds) === n} onPress={() => setRounds(n)} />
          ))}
        </Row>
      </View>

      {err ? <ErrorBox error={err} /> : null}
      {game.locked ? (
        <Button title="Unlock with Premium" icon="lock-open-outline" onPress={() => router.push('/premium')} />
      ) : (
        <Button title="Invite partner to play" icon="paper-plane-outline" onPress={start} loading={busy} />
      )}
      <Muted center>Your partner gets an invite and can join when they're ready.</Muted>

      <View style={{ gap: 8, marginTop: 8 }}>
        <T weight="700">Your own questions</T>
        <Muted>Add questions only you two will see. They're mixed into this game.</Muted>
        <Row>
          <View style={{ flex: 1 }}>
            <Field value={newQ} onChangeText={setNewQ} placeholder="Write a question" maxLength={500} />
          </View>
          <Button title="Add" onPress={addCustom} disabled={newQ.trim().length < 3} />
        </Row>
        {(custom.data ?? []).map((q) => (
          <Card key={q.id} style={{ paddingVertical: 10 }}>
            <Row>
              <T style={{ flex: 1 }}>{q.text}</T>
              <Pressable
                onPress={async () => {
                  try {
                    await api('DELETE', `/games/custom-questions/${q.id}`);
                    custom.reload();
                  } catch (e) {
                    setErr(errorText(e));
                  }
                }}
                hitSlop={10}
                accessibilityLabel="Delete question"
              >
                <Ionicons name="trash-outline" size={18} color={c.muted} />
              </Pressable>
            </Row>
          </Card>
        ))}
      </View>
    </Screen>
  );
}
