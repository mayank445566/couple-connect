import { Ionicons } from '@expo/vector-icons';
import { useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import React from 'react';
import { Pressable, View } from 'react-native';
import { authedSource } from '../../lib/api';
import { T } from '../ui';

const fmt = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

/** Plays a private voice note; the bearer token goes in the request headers, never in the URL. */
export function VoiceNote({ url, durationMs, color }: { url: string; durationMs: number | null; color: string }) {
  const player = useAudioPlayer(authedSource(url) as any);
  const st = useAudioPlayerStatus(player);
  const total = st.duration || (durationMs ?? 0) / 1000;
  const progress = total > 0 ? Math.min(1, st.currentTime / total) : 0;

  const toggle = () => {
    if (st.playing) player.pause();
    else {
      if (st.didJustFinish || (total > 0 && st.currentTime >= total - 0.1)) player.seekTo(0);
      player.play();
    }
  };

  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, minWidth: 180 }}>
      <Pressable onPress={toggle} hitSlop={8} accessibilityLabel={st.playing ? 'Pause voice note' : 'Play voice note'}>
        <Ionicons name={st.playing ? 'pause-circle' : 'play-circle'} size={36} color={color} />
      </Pressable>
      <View style={{ flex: 1, gap: 4 }}>
        <View style={{ height: 4, borderRadius: 2, backgroundColor: color + '44' }}>
          <View style={{ height: 4, borderRadius: 2, width: `${progress * 100}%`, backgroundColor: color }} />
        </View>
        <T size={11} color={color}>{fmt(st.playing || st.currentTime > 0 ? st.currentTime : total)}</T>
      </View>
    </View>
  );
}
