import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import React, { memo } from 'react';
import { Pressable, View } from 'react-native';
import { authedSource, Message } from '../../lib/api';
import type { OutboxItem } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';
import { clock, T } from '../ui';
import { VoiceNote } from './VoiceNote';

function readable(bg: string) {
  // Pick white or near-black text for a bubble colour
  const h = bg.replace('#', '');
  if (h.length < 6) return '#FFFFFF';
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
  return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#1A1A1A' : '#FFFFFF';
}

export function preview(m: Pick<Message, 'type' | 'body'>): string {
  switch (m.type) {
    case 'image': return m.body ? `📷 ${m.body}` : '📷 Photo';
    case 'video': return '🎬 Video';
    case 'voice': return '🎤 Voice note';
    case 'audio': return '🎵 Audio';
    case 'file': return '📎 File';
    case 'gif': return 'GIF';
    case 'sticker': return 'Sticker';
    case 'deleted': return 'Message deleted';
    default: return m.body ?? '';
  }
}

type Props = {
  m: Message;
  replyTo?: Message;
  onLongPress: (m: Message) => void;
  onReplyPress?: (id: number) => void;
  highlighted?: boolean;
};

export const MessageBubble = memo(function MessageBubble({ m, replyTo, onLongPress, onReplyPress, highlighted }: Props) {
  const { c } = useTheme();
  const bg = m.mine ? c.bubble_mine : c.bubble_theirs;
  const fg = readable(bg);
  const media = m.media;
  const isMedia = m.type === 'image' || m.type === 'video' || m.type === 'gif';
  const w = media?.width && media?.height ? Math.min(240, media.width) : 220;
  const h = media?.width && media?.height ? (w * media.height) / media.width : 220;

  // Group reactions by emoji
  const reacts = Object.entries(m.reactions.reduce<Record<string, number>>((a, r) => ((a[r.emoji] = (a[r.emoji] ?? 0) + 1), a), {}));

  return (
    <View style={{ alignItems: m.mine ? 'flex-end' : 'flex-start', paddingHorizontal: 12, marginVertical: 3, backgroundColor: highlighted ? c.primary + '22' : undefined }}>
      <Pressable
        onLongPress={() => !m.deleted && onLongPress(m)}
        delayLongPress={300}
        onPress={() => {
          if (!media || m.deleted) return;
          if (m.type === 'image' || m.type === 'video' || m.type === 'file' || m.type === 'audio') router.push({ pathname: '/media', params: { id: String(media.id), type: m.type, mime: media.mime, name: media.name ?? '' } });
        }}
        style={{ maxWidth: '80%', backgroundColor: m.deleted ? 'transparent' : bg, borderWidth: m.deleted ? 1 : 0, borderColor: c.border, borderRadius: 20, borderBottomRightRadius: m.mine ? 6 : 20, borderBottomLeftRadius: m.mine ? 20 : 6, padding: isMedia ? 4 : 10, paddingHorizontal: isMedia ? 4 : 14, gap: 4 }}
        accessibilityHint="Long press for options"
      >
        {m.forwarded ? <T size={11} color={fg + 'AA'}>↪ Forwarded</T> : null}
        {replyTo ? (
          <Pressable onPress={() => onReplyPress?.(replyTo.id)} style={{ borderLeftWidth: 3, borderLeftColor: fg + '88', paddingLeft: 8, paddingVertical: 2, marginHorizontal: isMedia ? 6 : 0, marginTop: isMedia ? 6 : 0 }}>
            <T size={11} weight="700" color={fg + 'CC'}>{replyTo.mine ? 'You' : 'Partner'}</T>
            <T size={12} color={fg + 'CC'} numberOfLines={2}>{preview(replyTo)}</T>
          </Pressable>
        ) : null}

        {m.deleted ? (
          <T size={14} color={c.muted} style={{ fontStyle: 'italic' }}>🚫 This message was deleted</T>
        ) : (
          <>
            {m.type === 'image' && media ? <Image source={authedSource(media.url)} style={{ width: w, height: Math.min(h, 320), borderRadius: 16 }} contentFit="cover" transition={150} /> : null}
            {m.type === 'gif' && m.meta?.url ? <Image source={{ uri: m.meta.url }} style={{ width: 220, height: 220 * ((m.meta.height || 1) / (m.meta.width || 1)), borderRadius: 16 }} contentFit="cover" /> : null}
            {m.type === 'video' && media ? (
              <View style={{ width: 220, height: 160, borderRadius: 16, backgroundColor: '#000', alignItems: 'center', justifyContent: 'center' }}>
                <Ionicons name="play-circle" size={52} color="#fff" />
                {media.duration_ms ? <T size={11} color="#fff">{Math.round(media.duration_ms / 1000)}s</T> : null}
              </View>
            ) : null}
            {(m.type === 'voice' || m.type === 'audio') && media ? <VoiceNote url={media.url} durationMs={media.duration_ms} color={fg} /> : null}
            {m.type === 'file' && media ? (
              <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
                <Ionicons name="document-attach-outline" size={28} color={fg} />
                <View style={{ flexShrink: 1 }}>
                  <T color={fg} weight="600" numberOfLines={1}>{media.name ?? 'File'}</T>
                  <T size={11} color={fg + 'AA'}>{(media.size / 1024 / 1024).toFixed(1)} MB</T>
                </View>
              </View>
            ) : null}
            {m.type === 'sticker' ? <T size={48}>{m.meta?.emoji ?? '💖'}</T> : null}
            {m.body && m.type !== 'sticker' ? <T color={fg} size={15} style={{ paddingHorizontal: isMedia ? 8 : 0 }}>{m.body}</T> : null}
          </>
        )}

        <View style={{ flexDirection: 'row', alignSelf: 'flex-end', alignItems: 'center', gap: 4, paddingHorizontal: isMedia ? 8 : 0, paddingBottom: isMedia ? 4 : 0 }}>
          {m.pinned ? <Ionicons name="pin" size={11} color={fg + 'AA'} /> : null}
          {m.starred ? <Ionicons name="star" size={11} color={fg + 'AA'} /> : null}
          {m.expires_at ? <Ionicons name="timer-outline" size={11} color={fg + 'AA'} /> : null}
          {m.edited_at && !m.deleted ? <T size={10} color={fg + 'AA'}>edited</T> : null}
          <T size={10} color={m.deleted ? c.muted : fg + 'AA'}>{clock(m.created_at)}</T>
          {m.mine && !m.deleted ? <Ionicons name={m.read_at ? 'checkmark-done' : m.delivered_at ? 'checkmark-done' : 'checkmark'} size={14} color={m.read_at ? '#53BDEB' : fg + 'AA'} /> : null}
        </View>
      </Pressable>
      {reacts.length ? (
        <View style={{ flexDirection: 'row', gap: 4, marginTop: -6, backgroundColor: c.surface, borderRadius: 12, paddingHorizontal: 6, paddingVertical: 2, borderWidth: 1, borderColor: c.border }}>
          {reacts.map(([e, n]) => (
            <T key={e} size={13}>{e}{n > 1 ? ` ${n}` : ''}</T>
          ))}
        </View>
      ) : null}
    </View>
  );
});

/** A message still on the device: sending, queued offline, or failed. */
export function PendingBubble({ item, onRetry, onDiscard }: { item: OutboxItem; onRetry: () => void; onDiscard: () => void }) {
  const { c } = useTheme();
  const fg = readable(c.bubble_mine);
  return (
    <View style={{ alignItems: 'flex-end', paddingHorizontal: 12, marginVertical: 3 }}>
      <View style={{ maxWidth: '80%', backgroundColor: c.bubble_mine, opacity: 0.75, borderRadius: 20, borderBottomRightRadius: 6, padding: 10, paddingHorizontal: 14, gap: 4 }}>
        {item.local_uri && item.type === 'image' ? <Image source={{ uri: item.local_uri }} style={{ width: 200, height: 200, borderRadius: 14 }} /> : null}
        {item.type !== 'text' && !item.local_uri ? <T color={fg}>{preview({ type: item.type, body: null })}</T> : null}
        {item.body ? <T color={fg}>{item.body}</T> : null}
        <View style={{ flexDirection: 'row', alignSelf: 'flex-end', gap: 4, alignItems: 'center' }}>
          <Ionicons name={item.status === 'failed' ? 'alert-circle' : 'time-outline'} size={13} color={item.status === 'failed' ? '#FFD1D1' : fg + 'AA'} />
          <T size={10} color={fg + 'AA'}>{item.status === 'failed' ? 'Not sent' : 'Sending…'}</T>
        </View>
      </View>
      {item.status === 'failed' ? (
        <View style={{ flexDirection: 'row', gap: 14, marginTop: 4 }}>
          {item.error ? <T size={11} color={c.danger}>{item.error}</T> : null}
          <Pressable onPress={onRetry}><T size={12} weight="700" color={c.primary}>Retry</T></Pressable>
          <Pressable onPress={onDiscard}><T size={12} color={c.muted}>Discard</T></Pressable>
        </View>
      ) : null}
    </View>
  );
}
