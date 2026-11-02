import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { View } from 'react-native';
import { Button, Card, ErrorBox, Header, Muted, Pill, Row, Screen, SkeletonList, T, timeAgo, IconBubble } from '../../components/ui';
import { api } from '../../lib/api';
import { gameIcon } from '../../lib/constants';
import { GamesIndex, Session } from '../../lib/games';
import { useLoad } from '../../lib/useLoad';
import { useAuth } from '../../state/AuthProvider';
import { useSyncEvent } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

/** A different soft colour per game keeps the list lively. */
const ACCENTS = ['#FF4F86', '#FF8A3D', '#8E6CFF', '#20A99A', '#E9A100', '#3B82F6'];

export default function Games() {
  const { c } = useTheme();
  const { me } = useAuth();
  const [section, setSection] = useState<'free' | 'adult'>('free');
  const idx = useLoad(() => api<GamesIndex>('GET', '/games'));
  const sessions = useLoad(() => api<Session[] | { sessions: Session[] }>('GET', '/games/sessions'));
  useSyncEvent('game.', () => sessions.reload());
  useSyncEvent('premium.', () => idx.reload());

  const all = sessions.data ? (Array.isArray(sessions.data) ? sessions.data : sessions.data.sessions) : [];
  const live = all.filter((s) => s.status === 'invited' || s.status === 'active');
  const d = idx.data;

  return (
    <Screen refreshing={idx.refreshing} onRefresh={() => { idx.onRefresh(); sessions.reload(); }}>
      <Header title="Games" right={<Button title="Daily" variant="ghost" icon="help-circle-outline" onPress={() => router.push('/daily')} />} />

      {live.map((s) => {
        const invitedMe = s.status === 'invited' && s.started_by !== me?.id;
        return (
          <Card key={s.id} onPress={() => router.push(`/game/${s.id}`)} style={invitedMe ? { borderColor: c.primary, borderWidth: 2 } : undefined}>
            <Row>
              <Ionicons name="game-controller" size={22} color={c.primary} />
              <View style={{ flex: 1 }}>
                <T weight="700">{s.game.title}</T>
                <Muted>{invitedMe ? 'Your partner invited you. Tap to answer.' : s.status === 'invited' ? 'Waiting for your partner to join' : `Round ${s.round_no} of ${s.total_rounds}`}</Muted>
              </View>
              <Ionicons name="chevron-forward" size={20} color={c.muted} />
            </Row>
          </Card>
        );
      })}

      <Row gap={8}>
        <Pill label="Free games" active={section === 'free'} onPress={() => setSection('free')} />
        {d?.adult.available ? <Pill label="18+ for couples" icon="lock-closed-outline" active={section === 'adult'} onPress={() => setSection('adult')} /> : null}
      </Row>

      {idx.error ? <ErrorBox error={idx.error} onRetry={idx.reload} /> : null}
      {!d && !idx.error ? <SkeletonList rows={6} /> : null}

      {d && section === 'adult' ? <AdultGate status={d.adult} onChanged={idx.reload} /> : null}

      {d && (section === 'free' || (d.adult.my_opt_in && d.adult.partner_opt_in))
        ? d.games[section].map((g, i) => (
            <Card key={g.slug} onPress={() => router.push(`/games/${g.slug}`)}>
              <Row gap={14}>
                <IconBubble name={gameIcon(g.icon)} size={50} color={section === 'adult' ? undefined : ACCENTS[i % ACCENTS.length]} filled={section === 'adult'} />
                <View style={{ flex: 1 }}>
                  <T weight="700">{g.title}</T>
                  <Muted>{g.description}</Muted>
                </View>
                {g.locked ? <Ionicons name="lock-closed" size={18} color={c.muted} /> : null}
              </Row>
            </Card>
          ))
        : null}

      {all.filter((s) => s.status === 'finished').length ? (
        <View style={{ gap: 8 }}>
          <T weight="700">Recently played</T>
          {all
            .filter((s) => s.status === 'finished')
            .slice(0, 5)
            .map((s) => (
              <Card key={s.id} onPress={() => router.push(`/game/${s.id}`)} style={{ paddingVertical: 10 }}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <T>{s.game.title}</T>
                  <Muted>See results</Muted>
                </Row>
              </Card>
            ))}
        </View>
      ) : null}
    </Screen>
  );
}

/** Explains and collects 18+ consent. Both partners must opt in separately; either can opt out at any time. */
function AdultGate({ status, onChanged }: { status: GamesIndex['adult']; onChanged: () => void }) {
  if (status.my_opt_in && status.partner_opt_in) {
    return status.premium ? null : (
      <Card>
        <T weight="700">Unlock 18+ games</T>
        <Muted>A one-time ₹199 purchase unlocks premium games and themes for both of you, for life.</Muted>
        <Button title="See what's included" onPress={() => router.push('/premium')} />
      </Card>
    );
  }
  return (
    <Card>
      <T weight="700">18+ games for couples</T>
      <Muted>
        These games are private, for consenting adults, and only playable when both of you choose to turn them on. You can skip any question or stop any game at any time, and switch this off whenever you like. Notifications never show their content.
      </Muted>
      {status.my_opt_in ? (
        <Muted>You're in. Waiting for your partner to opt in from their own phone.</Muted>
      ) : (
        <Button title="I'm 18+ and want to opt in" onPress={() => router.push('/adult')} />
      )}
    </Card>
  );
}
