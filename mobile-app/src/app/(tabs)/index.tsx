import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { Avatar, Card, ErrorBox, Hero, Muted, Row, Screen, SkeletonList, T, timeAgo } from '../../components/ui';
import { api, Partner } from '../../lib/api';
import { daysUntil, moodOf, tapLabel, TAPS } from '../../lib/constants';
import { useLoad } from '../../lib/useLoad';
import { useSync, useSyncEvent } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

type Home = {
  partner: Partner & { mood_today: string | null };
  my_mood_today: string | null;
  days_together: number;
  relationship_start: string;
  next_milestone: { days: number; in_days: number; date: string } | null;
  next_meeting_at: string | null;
  next_countdown: { id: number; title: string; target_at: string; emoji: string | null } | null;
  anniversary: { date: string; in_days: number } | null;
  partner_birthday: { date: string; in_days: number } | null;
  upcoming: { id: number; title: string; kind: string; next_date: string }[];
  memory_of_the_day: { id: number; title: string; happened_on: string; body: string | null; media_url?: string | null } | null;
  daily_question: { i_answered: boolean; partner_answered: boolean };
  streak: number;
  game_invites: number;
  last_tap_from_partner: { kind: string; at: string } | null;
  unread_messages: number;
  bucket: { total: number | string; done: number | string | null };
};

export default function HomeTab() {
  const { c } = useTheme();
  const { partner: presence } = useSync();
  const { data, error, reload, refreshing, onRefresh } = useLoad(() => api<Home>('GET', '/home'));
  const [sent, setSent] = useState<string | null>(null);
  useSyncEvent('tap.', () => reload());
  useSyncEvent('mood.', () => reload());
  useSyncEvent('daily.', () => reload());
  useSyncEvent('game.', () => reload());
  useSyncEvent('hub.', () => reload());
  useSyncEvent('couple.', () => reload());

  const tap = async (kind: string) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    try {
      await api('POST', '/taps', { kind });
      setSent(kind);
      setTimeout(() => setSent(null), 2000);
    } catch {}
  };

  if (!data) {
    return <Screen>{error ? <ErrorBox error={error} onRetry={reload} /> : <SkeletonList rows={5} />}</Screen>;
  }

  const pMood = moodOf(data.partner.mood_today);
  const myMood = moodOf(data.my_mood_today);
  const status = presence.typing ? 'typing…' : presence.online ? 'online' : presence.last_seen_at ? `last seen ${timeAgo(presence.last_seen_at)}` : '';

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Hero>
        <Row>
          <Avatar url={data.partner.avatar_url} name={data.partner.display_name} size={56} />
          <View style={{ flex: 1 }}>
            <T size={20} weight="700" color="#fff">{data.partner.display_name}</T>
            {status ? <T size={13} color="#ffffffCC">{status}</T> : null}
          </View>
          {pMood ? <T size={32}>{pMood.emoji}</T> : null}
        </Row>
        <T size={40} weight="800" color="#fff" style={{ marginTop: 8 }}>{data.days_together.toLocaleString()} days</T>
        <T color="#ffffffDD">together{data.next_milestone ? ` · ${data.next_milestone.days} days in ${data.next_milestone.in_days}` : ''}</T>
      </Hero>

      {data.last_tap_from_partner ? (
        <Card>
          <Row>
            <T size={28}>{tapLabel(data.last_tap_from_partner.kind).emoji}</T>
            <View style={{ flex: 1 }}>
              <T weight="600">{data.partner.display_name} sent a {tapLabel(data.last_tap_from_partner.kind).label.toLowerCase()}</T>
              <Muted>{timeAgo(data.last_tap_from_partner.at)}</Muted>
            </View>
          </Row>
        </Card>
      ) : null}

      <View style={{ gap: 8 }}>
        <T weight="700">Send a little something</T>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 10 }}>
          {TAPS.map((t) => (
            <Pressable key={t.kind} onPress={() => tap(t.kind)} accessibilityLabel={`Send ${t.label}`} style={({ pressed }) => ({ alignItems: 'center', width: 76, padding: 10, borderRadius: 18, backgroundColor: sent === t.kind ? c.primary + '33' : c.surface, borderWidth: 1, borderColor: c.border, transform: [{ scale: pressed ? 0.94 : 1 }] })}>
              <T size={26}>{sent === t.kind ? '✓' : t.emoji}</T>
              <T size={11} center numberOfLines={2}>{t.label}</T>
            </Pressable>
          ))}
        </ScrollView>
      </View>

      <Row gap={12}>
        <Card style={{ flex: 1 }} onPress={() => router.push('/daily')}>
          <Ionicons name="help-circle-outline" size={24} color={c.primary} />
          <T weight="700">Daily question</T>
          <Muted>{data.daily_question.i_answered ? (data.daily_question.partner_answered ? 'You both answered' : 'Waiting for partner') : data.daily_question.partner_answered ? 'Partner answered. Your turn!' : 'Answer today'}</Muted>
          {data.streak > 0 ? <T size={13} weight="600" color={c.primary}>🔥 {data.streak}-day streak</T> : null}
        </Card>
        <Card style={{ flex: 1 }} onPress={() => router.push('/mood')}>
          <T size={24}>{myMood?.emoji ?? '🙂'}</T>
          <T weight="700">Your mood</T>
          <Muted>{myMood ? myMood.label : 'Share how you feel'}</Muted>
          {pMood ? <Muted>Partner: {pMood.label}</Muted> : null}
        </Card>
      </Row>

      {data.game_invites > 0 ? (
        <Card onPress={() => router.push('/games')} style={{ borderColor: c.primary, borderWidth: 2 }}>
          <Row>
            <Ionicons name="game-controller" size={24} color={c.primary} />
            <T weight="700" style={{ flex: 1 }}>{data.partner.display_name} invited you to play</T>
            <Ionicons name="chevron-forward" size={20} color={c.muted} />
          </Row>
        </Card>
      ) : null}

      {data.next_meeting_at && daysUntil(data.next_meeting_at) >= 0 ? (
        <Card>
          <Muted>Next time you meet</Muted>
          <T size={22} weight="700">{daysUntil(data.next_meeting_at) === 0 ? 'Today! 💞' : `${daysUntil(data.next_meeting_at)} days`}</T>
          <Muted>{new Date(data.next_meeting_at).toLocaleString()}</Muted>
        </Card>
      ) : null}

      <Row gap={12} style={{ flexWrap: 'wrap' }}>
        {data.next_countdown ? <Mini label={data.next_countdown.title} value={`${data.next_countdown.emoji ?? '⏳'} ${Math.max(0, daysUntil(data.next_countdown.target_at))}d`} onPress={() => router.push('/hub/countdowns')} /> : null}
        {data.anniversary ? <Mini label="Anniversary" value={`💍 ${data.anniversary.in_days}d`} /> : null}
        {data.partner_birthday ? <Mini label={`${data.partner.display_name}'s birthday`} value={`🎂 ${data.partner_birthday.in_days}d`} /> : null}
        <Mini label="Bucket list" value={`✅ ${Number(data.bucket.done ?? 0)}/${Number(data.bucket.total)}`} onPress={() => router.push('/hub/bucket')} />
      </Row>

      {data.memory_of_the_day ? (
        <Card onPress={() => router.push('/hub/memories')}>
          <Muted>On this day · {data.memory_of_the_day.happened_on}</Muted>
          <T weight="700">{data.memory_of_the_day.title}</T>
          {data.memory_of_the_day.body ? <Muted>{data.memory_of_the_day.body}</Muted> : null}
        </Card>
      ) : null}

      {data.upcoming.length ? (
        <Card onPress={() => router.push('/hub/calendar')}>
          <T weight="700">Coming up</T>
          {data.upcoming.map((e) => (
            <Row key={e.id + e.next_date} style={{ justifyContent: 'space-between' }}>
              <T style={{ flex: 1 }} numberOfLines={1}>{e.title}</T>
              <Muted>{e.next_date}</Muted>
            </Row>
          ))}
        </Card>
      ) : null}
    </Screen>
  );
}

function Mini({ label, value, onPress }: { label: string; value: string; onPress?: () => void }) {
  return (
    <Card style={{ minWidth: 150, flexGrow: 1, gap: 2 }} onPress={onPress}>
      <T size={20} weight="700">{value}</T>
      <Muted>{label}</Muted>
    </Card>
  );
}
