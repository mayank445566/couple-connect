import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useRef, useState } from 'react';
import { Animated, Pressable, ScrollView, View } from 'react-native';
import { useFloatingHearts, useHeartbeat } from '../../components/FloatingHearts';
import { Avatar, Card, cardStyle, ErrorBox, Hero, IconBubble, IconName, Muted, Row, Screen, SectionTitle, SkeletonList, T, timeAgo } from '../../components/ui';
import { api, authedSource, Partner } from '../../lib/api';
import { useAuth } from '../../state/AuthProvider';
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

function greeting() {
  const h = new Date().getHours();
  return h < 5 ? 'Up late' : h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

/** A round-number day or the anniversary itself: worth a celebration. */
function celebration(d: Home): { title: string; text: string } | null {
  if (d.anniversary && d.anniversary.in_days === 0) return { title: 'Happy anniversary! 💍', text: 'Another year of you two. Make today special.' };
  if (d.days_together > 0 && (d.days_together % 100 === 0 || d.days_together === 30 || d.days_together === 365)) return { title: `${d.days_together.toLocaleString()} days together 🎉`, text: 'A little milestone worth celebrating.' };
  if (d.partner_birthday && d.partner_birthday.in_days === 0) return { title: `It’s ${d.partner.display_name}’s birthday 🎂`, text: 'Send some love their way.' };
  return null;
}

export default function HomeTab() {
  const { c } = useTheme();
  const { me } = useAuth();
  const { partner: presence } = useSync();
  const { data, error, reload, refreshing, onRefresh } = useLoad(() => api<Home>('GET', '/home'));
  const [sent, setSent] = useState<string | null>(null);
  const hearts = useFloatingHearts();
  const beat = useHeartbeat();
  const celebrated = useRef(false);
  useSyncEvent('tap.', (e) => {
    reload();
    // A tap from my partner floats onto the screen
    if (e.actor_id !== me?.id) hearts.fire(tapLabel(e.payload?.kind ?? '').emoji, 0.5, 7);
  });
  useSyncEvent('mood.', () => reload());
  useSyncEvent('daily.', () => reload());
  useSyncEvent('game.', () => reload());
  useSyncEvent('hub.', () => reload());
  useSyncEvent('couple.', () => reload());

  // First time on Home after pairing: a one-off welcome
  const [welcome, setWelcome] = useState(false);
  const partnerId = data?.partner.id;
  useEffect(() => {
    if (!partnerId) return;
    const key = `cc_welcomed_${partnerId}`;
    AsyncStorage.getItem(key)
      .then((v) => {
        if (v) return;
        AsyncStorage.setItem(key, '1');
        setWelcome(true);
        setTimeout(() => hearts.fire('💞', 0.5, 12), 300);
      })
      .catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [partnerId]);

  const party = data ? celebration(data) : null;
  useEffect(() => {
    if (party && !celebrated.current) {
      celebrated.current = true;
      setTimeout(() => hearts.fire('🎉', 0.3, 10), 400);
      setTimeout(() => hearts.fire('💖', 0.7, 10), 700);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [party?.title]);

  const tap = async (kind: string, index: number) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    hearts.fire(tapLabel(kind).emoji, Math.min(0.85, 0.12 + index * 0.2));
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
  const status = presence.typing ? 'typing…' : presence.online ? 'online now' : presence.last_seen_at ? `last seen ${timeAgo(presence.last_seen_at)}` : '';
  const ms = data.next_milestone;
  const progress = ms ? Math.max(0.04, Math.min(1, data.days_together / ms.days)) : 0;
  const meetIn = data.next_meeting_at ? daysUntil(data.next_meeting_at) : null;

  return (
    <View style={{ flex: 1 }}>
      <Screen refreshing={refreshing} onRefresh={onRefresh}>
        <Row style={{ justifyContent: 'space-between' }}>
          <View style={{ flexShrink: 1 }}>
            <Muted size={14}>{greeting()},</Muted>
            <T serif size={28} numberOfLines={1}>{me?.display_name ?? 'you'}</T>
          </View>
          <Pressable onPress={() => router.push('/settings')} accessibilityLabel="Settings" style={[cardStyle(c), { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' }]}>
            <Ionicons name="settings-outline" size={20} color={c.text} />
          </Pressable>
        </Row>

        <Hero style={{ paddingTop: 24, paddingBottom: 22 }}>
          <Row style={{ justifyContent: 'center', gap: 0 }}>
            <View style={{ borderRadius: 40, borderWidth: 3, borderColor: '#ffffffCC' }}><Avatar url={me?.avatar_url} name={me?.display_name} size={64} /></View>
            <Animated.View style={{ zIndex: 2, marginHorizontal: -10, width: 36, height: 36, borderRadius: 18, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', transform: [{ scale: beat }] }}>
              <Ionicons name="heart" size={20} color={c.primary} />
            </Animated.View>
            <View style={{ borderRadius: 40, borderWidth: 3, borderColor: '#ffffffCC' }}><Avatar url={data.partner.avatar_url} name={data.partner.display_name} size={64} /></View>
          </Row>
          <T serif size={64} color="#fff" center style={{ marginTop: 8, lineHeight: 72 }}>{data.days_together.toLocaleString()}</T>
          <T center color="#ffffffE6" weight="600">days together</T>
          {ms ? (
            <View style={{ marginTop: 14, gap: 6 }}>
              <View style={{ height: 6, borderRadius: 3, backgroundColor: '#ffffff40', overflow: 'hidden' }}>
                <View style={{ width: `${progress * 100}%`, height: 6, borderRadius: 3, backgroundColor: '#fff' }} />
              </View>
              <Row style={{ justifyContent: 'space-between' }}>
                <T size={12} color="#ffffffCC">since {new Date(data.relationship_start).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}</T>
                <T size={12} color="#ffffffCC" weight="600">{ms.days.toLocaleString()} days in {ms.in_days}</T>
              </Row>
            </View>
          ) : null}
        </Hero>

        {welcome ? (
          <Card style={{ borderWidth: 1.5, borderColor: c.primary + '55' }}>
            <T serif size={20}>You’re connected 💞</T>
            <Muted>This is your shared space with {data.partner.display_name}. Send a love tap, answer today’s question, or say hi in Chat.</Muted>
          </Card>
        ) : null}

        {party ? (
          <Card style={{ borderWidth: 1.5, borderColor: c.primary + '55' }}>
            <T serif size={20}>{party.title}</T>
            <Muted>{party.text}</Muted>
          </Card>
        ) : null}

        <Card onPress={() => router.push('/chat')} style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <View>
            <Avatar url={data.partner.avatar_url} name={data.partner.display_name} size={48} />
            {presence.online ? <View style={{ position: 'absolute', right: 0, bottom: 0, width: 14, height: 14, borderRadius: 7, backgroundColor: c.success, borderWidth: 2.5, borderColor: c.surface }} /> : null}
          </View>
          <View style={{ flex: 1 }}>
            <T weight="700" size={16}>{data.partner.display_name}</T>
            <T size={13} color={presence.typing || presence.online ? c.primary : c.muted} numberOfLines={1}>
              {data.last_tap_from_partner ? `${tapLabel(data.last_tap_from_partner.kind).emoji} sent you a ${tapLabel(data.last_tap_from_partner.kind).label.toLowerCase()} · ${timeAgo(data.last_tap_from_partner.at)}` : status || 'Say hi'}
            </T>
          </View>
          {data.unread_messages > 0 ? (
            <LinearGradient colors={c.grad} style={{ minWidth: 24, height: 24, borderRadius: 12, paddingHorizontal: 7, alignItems: 'center', justifyContent: 'center' }}>
              <T size={12} weight="800" color="#fff">{data.unread_messages > 99 ? '99+' : data.unread_messages}</T>
            </LinearGradient>
          ) : (
            <IconBubble name="chatbubble-ellipses" size={40} />
          )}
        </Card>

        <SectionTitle>Send a little love</SectionTitle>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 12, paddingVertical: 2, paddingHorizontal: 2 }} style={{ marginHorizontal: -2 }}>
          {TAPS.map((t, i) => (
            <Pressable key={t.kind} onPress={() => tap(t.kind, i)} accessibilityLabel={`Send ${t.label}`} style={({ pressed }) => ({ alignItems: 'center', gap: 6, width: 70, transform: [{ scale: pressed ? 0.9 : 1 }] })}>
              <View style={[cardStyle(c), { width: 62, height: 62, borderRadius: 31, alignItems: 'center', justifyContent: 'center', borderWidth: sent === t.kind ? 2 : 0, borderColor: c.primary }]}>
                <T size={28}>{sent === t.kind ? '💌' : t.emoji}</T>
              </View>
              <T size={11} weight="600" center numberOfLines={1} color={c.muted}>{t.label}</T>
            </Pressable>
          ))}
        </ScrollView>

        <Row gap={12} style={{ alignItems: 'stretch' }}>
          <Card style={{ flex: 1 }} onPress={() => router.push('/daily')}>
            <IconBubble name="chatbubbles" filled size={42} />
            <T weight="700" size={15} style={{ marginTop: 4 }}>Daily question</T>
            <Muted>{data.daily_question.i_answered ? (data.daily_question.partner_answered ? 'You both answered 💞' : 'Waiting for partner') : data.daily_question.partner_answered ? 'Partner answered. Your turn!' : 'Answer today'}</Muted>
            {data.streak > 0 ? <T size={13} weight="700" color={c.primary}>🔥 {data.streak}-day streak</T> : null}
          </Card>
          <Card style={{ flex: 1 }} onPress={() => router.push('/mood')}>
            <Row gap={-6}>
              <View style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: c.soft, alignItems: 'center', justifyContent: 'center' }}><T size={22}>{myMood?.emoji ?? '🙂'}</T></View>
              <View style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: c.surface2, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: c.surface }}><T size={22}>{pMood?.emoji ?? '❔'}</T></View>
            </Row>
            <T weight="700" size={15} style={{ marginTop: 4 }}>Moods today</T>
            <Muted>You: {myMood ? myMood.label : 'not set'}</Muted>
            <Muted>{data.partner.display_name}: {pMood ? pMood.label : 'not set'}</Muted>
          </Card>
        </Row>

        {data.game_invites > 0 ? (
          <Card onPress={() => router.push('/games')} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: 1.5, borderColor: c.primary + '66' }}>
            <IconBubble name="game-controller" filled />
            <T weight="700" style={{ flex: 1 }}>{data.partner.display_name} invited you to play</T>
            <Ionicons name="chevron-forward" size={20} color={c.muted} />
          </Card>
        ) : null}

        {meetIn !== null && meetIn >= 0 ? (
          <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
            <View style={{ alignItems: 'center', minWidth: 64 }}>
              <T serif size={40} color={c.primary} style={{ lineHeight: 46 }}>{meetIn === 0 ? '💞' : meetIn}</T>
              {meetIn > 0 ? <T size={11} weight="700" color={c.muted} style={{ letterSpacing: 1 }}>{meetIn === 1 ? 'DAY' : 'DAYS'}</T> : null}
            </View>
            <View style={{ flex: 1 }}>
              <T weight="700" size={15}>{meetIn === 0 ? 'You meet today!' : 'Until you meet again'}</T>
              <Muted>{new Date(data.next_meeting_at!).toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })}</Muted>
            </View>
          </Card>
        ) : null}

        <SectionTitle>Your little world</SectionTitle>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
          {data.next_countdown ? <Tile icon="hourglass" label={data.next_countdown.title} value={`${Math.max(0, daysUntil(data.next_countdown.target_at))}d`} emoji={data.next_countdown.emoji ?? undefined} onPress={() => router.push('/hub/countdowns')} /> : null}
          {data.anniversary ? <Tile icon="diamond" label="Anniversary" value={data.anniversary.in_days === 0 ? 'Today' : `${data.anniversary.in_days}d`} onPress={() => router.push('/couple-dates')} /> : null}
          {data.partner_birthday ? <Tile icon="gift" label={`${data.partner.display_name}’s birthday`} value={data.partner_birthday.in_days === 0 ? 'Today' : `${data.partner_birthday.in_days}d`} /> : null}
          <Tile icon="checkmark-done" label="Bucket list" value={`${Number(data.bucket.done ?? 0)}/${Number(data.bucket.total)}`} onPress={() => router.push('/hub/bucket')} />
          {!data.anniversary ? <Tile icon="calendar" label="Add your dates" value="+" onPress={() => router.push('/couple-dates')} /> : null}
        </View>

        {data.memory_of_the_day ? (
          <Card onPress={() => router.push('/hub/memories')} style={{ padding: 0, overflow: 'hidden', gap: 0 }}>
            {data.memory_of_the_day.media_url ? <Image source={authedSource(data.memory_of_the_day.media_url)} style={{ width: '100%', height: 180 }} contentFit="cover" /> : null}
            <View style={{ padding: 18, gap: 4 }}>
              <T size={12} weight="700" color={c.primary} style={{ letterSpacing: 1.2 }}>ON THIS DAY · {new Date(data.memory_of_the_day.happened_on).getFullYear()}</T>
              <T serif size={20}>{data.memory_of_the_day.title}</T>
              {data.memory_of_the_day.body ? <Muted>{data.memory_of_the_day.body}</Muted> : null}
            </View>
          </Card>
        ) : null}

        {data.upcoming.length ? (
          <Card onPress={() => router.push('/hub/calendar')}>
            <Row style={{ justifyContent: 'space-between' }}>
              <T weight="700" size={15}>Coming up</T>
              <Ionicons name="chevron-forward" size={18} color={c.muted} />
            </Row>
            {data.upcoming.map((e) => (
              <Row key={e.id + e.next_date} style={{ justifyContent: 'space-between', paddingVertical: 4 }}>
                <Row style={{ flex: 1 }}>
                  <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: c.primary }} />
                  <T style={{ flex: 1 }} numberOfLines={1}>{e.title}</T>
                </Row>
                <Muted>{new Date(e.next_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}</Muted>
              </Row>
            ))}
          </Card>
        ) : null}
      </Screen>
      {hearts.layer}
    </View>
  );
}

function Tile({ icon, label, value, emoji, onPress }: { icon: IconName; label: string; value: string; emoji?: string; onPress?: () => void }) {
  return (
    <Card style={{ flexBasis: '46%', flexGrow: 1, gap: 6 }} onPress={onPress}>
      {emoji ? <T size={26}>{emoji}</T> : <IconBubble name={icon} size={38} />}
      <T serif size={26}>{value}</T>
      <Muted numberOfLines={1}>{label}</Muted>
    </Card>
  );
}
