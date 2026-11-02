import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import * as Haptics from 'expo-haptics';
import { router, useFocusEffect } from 'expo-router';
import React, { useCallback, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, KeyboardAvoidingView, Modal, Platform, Pressable, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Composer } from '../../components/chat/Composer';
import { MessageBubble, PendingBubble, preview } from '../../components/chat/MessageBubble';
import { Avatar, cardStyle, Empty, errorText, Glow, Muted, Row, T, timeAgo } from '../../components/ui';
import { api, Couple, Message } from '../../lib/api';
import { REACTIONS } from '../../lib/constants';
import { useLoad } from '../../lib/useLoad';
import { useAuth } from '../../state/AuthProvider';
import { OutboxItem, useSync } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';
import { t } from '../../lib/i18n';

type Row_ = { kind: 'msg'; m: Message } | { kind: 'pending'; p: OutboxItem } | { kind: 'day'; label: string; key: string };

function dayLabel(iso: string) {
  const d = new Date(iso);
  const today = new Date();
  const y = new Date(Date.now() - 86400000);
  if (d.toDateString() === today.toDateString()) return 'Today';
  if (d.toDateString() === y.toDateString()) return 'Yesterday';
  return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
}

export default function Chat() {
  const { c } = useTheme();
  const { me } = useAuth();
  const { messages, outbox, partner, loaded, hasOlder, offline, setChatOpen, loadOlder, retry, discard, upsert, remove } = useSync();
  const couple = useLoad(() => api<Couple>('GET', '/couple'));
  const [sel, setSel] = useState<Message | null>(null);
  const [replyTo, setReplyTo] = useState<Message | null>(null);
  const [editing, setEditing] = useState<Message | null>(null);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [highlight, setHighlight] = useState<number | null>(null);
  const list = useRef<FlatList<Row_>>(null);

  useFocusEffect(
    useCallback(() => {
      setChatOpen(true);
      return () => setChatOpen(false);
    }, [setChatOpen]),
  );

  const byId = useMemo(() => new Map(messages.map((m) => [m.id, m])), [messages]);
  const pinned = useMemo(() => messages.filter((m) => m.pinned && !m.deleted), [messages]);

  // Inverted list: newest first, with day separators after each day's oldest message
  const rows = useMemo<Row_[]>(() => {
    const out: Row_[] = [...outbox].reverse().map((p) => ({ kind: 'pending' as const, p }));
    messages.forEach((m, i) => {
      out.push({ kind: 'msg', m });
      const next = messages[i + 1];
      if (!next || new Date(next.created_at).toDateString() !== new Date(m.created_at).toDateString()) {
        out.push({ kind: 'day', label: dayLabel(m.created_at), key: 'd' + m.id });
      }
    });
    return out;
  }, [messages, outbox]);

  const jumpTo = (id: number) => {
    const idx = rows.findIndex((r) => r.kind === 'msg' && r.m.id === id);
    if (idx < 0) return Alert.alert(t('Older message'), t('Scroll up to load older messages.'));
    list.current?.scrollToIndex({ index: idx, viewPosition: 0.5, animated: true });
    setHighlight(id);
    setTimeout(() => setHighlight(null), 1500);
  };

  const act = async (fn: () => Promise<any>) => {
    setSel(null);
    try {
      const r = await fn();
      if (r && typeof r === 'object' && 'id' in r && 'client_id' in r) upsert(r as Message);
    } catch (e) {
      Alert.alert(t('Something went wrong'), errorText(e));
    }
  };

  const startCall = async (kind: 'audio' | 'video') => {
    try {
      const call = await api<{ id: number }>('POST', '/calls', { kind });
      router.push(`/call/${call.id}`);
    } catch (e) {
      // Already in a call (maybe on another device): open it instead
      const existing = (e as any)?.fields?.call_id;
      if (existing) router.push(`/call/${existing}`);
      else Alert.alert(t('Could not call'), errorText(e));
    }
  };

  const onLongPress = useCallback((m: Message) => {
    Haptics.selectionAsync().catch(() => {});
    setSel(m);
  }, []);

  const renderItem = useCallback(
    ({ item }: { item: Row_ }) => {
      if (item.kind === 'day') {
        return (
          <View style={{ alignItems: 'center', marginVertical: 10 }}>
            <View style={{ backgroundColor: c.soft, paddingHorizontal: 14, paddingVertical: 5, borderRadius: 999 }}>
              <T size={11.5} weight="700" color={c.primary}>{item.label}</T>
            </View>
          </View>
        );
      }
      if (item.kind === 'pending') return <PendingBubble item={item.p} onRetry={() => retry(item.p.client_id)} onDiscard={() => discard(item.p.client_id)} />;
      const m = item.m;
      return <MessageBubble m={m} replyTo={m.reply_to_id ? byId.get(m.reply_to_id) : undefined} onLongPress={onLongPress} onReplyPress={jumpTo} highlighted={highlight === m.id} />;
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [byId, c, highlight, onLongPress, retry, discard, rows],
  );

  const p = couple.data?.partner;
  const status = partner.typing ? 'typing…' : partner.online ? 'online' : partner.last_seen_at ? `last seen ${timeAgo(partner.last_seen_at)}` : '';
  const myId = me?.id;
  const iReacted = !!sel?.reactions.some((r) => r.user_id === myId);

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: c.background }} edges={['top']}>
      <Glow height={300} />
      <Row style={{ paddingHorizontal: 16, paddingVertical: 10 }}>
        <View>
          <Avatar url={p?.avatar_url} name={p?.display_name} size={44} ring />
          {partner.online ? <View style={{ position: 'absolute', right: 1, bottom: 1, width: 13, height: 13, borderRadius: 7, backgroundColor: c.success, borderWidth: 2.5, borderColor: c.background }} /> : null}
        </View>
        <View style={{ flex: 1 }}>
          <T weight="700" size={17}>{p?.display_name ?? 'Partner'}</T>
          <T size={12.5} weight={partner.typing ? '600' : '400'} color={partner.typing || partner.online ? c.primary : c.muted}>{offline ? 'Waiting for connection…' : status}</T>
        </View>
        {[
          ['call', 'Voice call', () => startCall('audio')],
          ['videocam', 'Video call', () => startCall('video')],
          ['search', 'Search, starred and pinned messages', () => router.push('/chat-search')],
        ].map(([icon, label, fn]) => (
          <Pressable key={label as string} onPress={fn as () => void} hitSlop={6} accessibilityLabel={label as string} style={({ pressed }) => ({ width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center', backgroundColor: c.soft, opacity: pressed ? 0.7 : 1 })}>
            <Ionicons name={icon as any} size={19} color={c.primary} />
          </Pressable>
        ))}
      </Row>

      {pinned.length ? (
        <Pressable onPress={() => jumpTo(pinned[0].id)} style={[cardStyle(c), { flexDirection: 'row', gap: 8, alignItems: 'center', paddingHorizontal: 14, paddingVertical: 10, marginHorizontal: 12, marginBottom: 6, borderRadius: 16 }]}>
          <Ionicons name="pin" size={14} color={c.primary} />
          <T raw size={13} numberOfLines={1} style={{ flex: 1 }}>{preview(pinned[0])}</T>
          {pinned.length > 1 ? <Muted>+{pinned.length - 1}</Muted> : null}
        </Pressable>
      ) : null}

      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {!loaded ? (
          <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
            <ActivityIndicator color={c.primary} />
          </View>
        ) : rows.length === 0 ? (
          <View style={{ flex: 1, justifyContent: 'center' }}>
            <Empty icon="chatbubbles-outline" title="Say hi 👋" text="This chat is only for the two of you." />
          </View>
        ) : (
          <FlatList
            ref={list}
            inverted
            data={rows}
            keyExtractor={(r) => (r.kind === 'msg' ? 'm' + r.m.id : r.kind === 'pending' ? 'p' + r.p.client_id : r.key)}
            renderItem={renderItem}
            contentContainerStyle={{ paddingVertical: 8 }}
            onEndReachedThreshold={0.3}
            onEndReached={async () => {
              if (!hasOlder || loadingOlder) return;
              setLoadingOlder(true);
              await loadOlder().catch(() => {});
              setLoadingOlder(false);
            }}
            ListFooterComponent={loadingOlder ? <ActivityIndicator color={c.primary} style={{ margin: 12 }} /> : undefined}
            onScrollToIndexFailed={() => {}}
            keyboardShouldPersistTaps="handled"
          />
        )}
        <Composer
          replyTo={replyTo}
          onCancelReply={() => setReplyTo(null)}
          editing={editing}
          onCancelEdit={() => setEditing(null)}
          onSaveEdit={async (m, body) => {
            upsert(await api<Message>('PATCH', `/messages/${m.id}`, { body }));
            setEditing(null);
          }}
        />
      </KeyboardAvoidingView>

      <Modal visible={!!sel} transparent animationType="fade" onRequestClose={() => setSel(null)}>
        <Pressable style={{ flex: 1, backgroundColor: '#0008', justifyContent: 'flex-end' }} onPress={() => setSel(null)}>
          {sel ? (
            <Pressable style={{ backgroundColor: c.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 16, gap: 4 }}>
              <Row style={{ justifyContent: 'space-around', paddingBottom: 10 }}>
                {REACTIONS.map((e) => {
                  const mine = sel.reactions.some((r) => r.emoji === e && r.user_id === myId);
                  return (
                    <Pressable key={e} onPress={() => act(() => api('POST', `/messages/${sel.id}/reactions`, { emoji: e }))} style={{ padding: 6, borderRadius: 20, backgroundColor: mine ? c.primary + '33' : undefined }}>
                      <T size={28}>{e}</T>
                    </Pressable>
                  );
                })}
              </Row>
              {[
                { icon: 'arrow-undo-outline', label: 'Reply', fn: () => { setReplyTo(sel); setSel(null); } },
                sel.body ? { icon: 'copy-outline', label: 'Copy', fn: () => { Clipboard.setStringAsync(sel.body ?? ''); setSel(null); } } : null,
                sel.mine && sel.type === 'text' ? { icon: 'create-outline', label: 'Edit', fn: () => { setEditing(sel); setReplyTo(null); setSel(null); } } : null,
                iReacted ? { icon: 'close-circle-outline', label: 'Remove my reaction', fn: () => act(() => api('DELETE', `/messages/${sel.id}/reactions`)) } : null,
                { icon: sel.starred ? 'star' : 'star-outline', label: sel.starred ? 'Unstar' : 'Star', fn: () => act(() => api(sel.starred ? 'DELETE' : 'POST', `/messages/${sel.id}/star`)) },
                { icon: sel.pinned ? 'pin' : 'pin-outline', label: sel.pinned ? 'Unpin' : 'Pin for both', fn: () => act(() => api(sel.pinned ? 'DELETE' : 'POST', `/messages/${sel.id}/pin`)) },
                { icon: 'trash-outline', label: 'Delete for me', fn: () => act(async () => { await api('DELETE', `/messages/${sel.id}?scope=me`); remove(sel.id); }) },
                sel.mine ? { icon: 'trash', label: 'Delete for everyone', danger: true, fn: () => act(() => api('DELETE', `/messages/${sel.id}?scope=everyone`)) } : null,
                !sel.mine ? { icon: 'flag-outline', label: 'Report message', danger: true, fn: () => { setSel(null); router.push({ pathname: '/report', params: { message_id: String(sel.id) } }); } } : null,
              ]
                .filter(Boolean)
                .map((a: any) => (
                  <Pressable key={a.label} onPress={a.fn} style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 14, paddingVertical: 12, opacity: pressed ? 0.6 : 1 })}>
                    <Ionicons name={a.icon} size={22} color={a.danger ? c.danger : c.text} />
                    <T color={a.danger ? c.danger : c.text}>{a.label}</T>
                  </Pressable>
                ))}
            </Pressable>
          ) : null}
        </Pressable>
      </Modal>
    </SafeAreaView>
  );
}
