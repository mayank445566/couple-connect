import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React from 'react';
import { Alert, View } from 'react-native';
import { Button, Card, Empty, ErrorBox, Header, Muted, Row, Screen, SkeletonList, T, errorText, timeAgo } from '../components/ui';
import { api } from '../lib/api';
import { useLoad } from '../lib/useLoad';
import { useSyncEvent } from '../state/SyncProvider';
import { useTheme } from '../state/ThemeProvider';
import type { Call } from './call/[id]';

export default function Calls() {
  const { c } = useTheme();
  const list = useLoad(() => api<(Call & { created_at: string })[]>('GET', '/calls'));
  useSyncEvent('call.', () => list.reload());

  const start = async (kind: 'audio' | 'video') => {
    try {
      const r = await api<{ id: number }>('POST', '/calls', { kind });
      router.push(`/call/${r.id}`);
    } catch (e) {
      Alert.alert('Could not call', errorText(e));
    }
  };

  return (
    <Screen refreshing={list.refreshing} onRefresh={list.onRefresh}>
      <Header title="Calls" onBack={() => router.back()} />
      <Row>
        <Button title="Voice call" icon="call" style={{ flex: 1 }} onPress={() => start('audio')} />
        <Button title="Video call" icon="videocam" style={{ flex: 1 }} onPress={() => start('video')} />
      </Row>
      <Muted>Calls go directly between your phones when possible. Nothing is recorded.</Muted>
      {list.error ? <ErrorBox error={list.error} onRetry={list.reload} /> : null}
      {!list.data && !list.error ? <SkeletonList /> : null}
      {list.data?.length === 0 ? <Empty icon="call-outline" title="No calls yet" /> : null}
      {list.data?.map((k) => {
        const missed = !k.outgoing && (k.status === 'missed' || k.status === 'cancelled');
        return (
          <Card key={k.id} style={{ paddingVertical: 12 }}>
            <Row>
              <Ionicons name={k.kind === 'video' ? 'videocam' : 'call'} size={20} color={missed ? c.danger : c.primary} />
              <View style={{ flex: 1 }}>
                <T weight="600" color={missed ? c.danger : c.text}>{k.outgoing ? 'Outgoing' : 'Incoming'} {k.kind === 'video' ? 'video' : 'voice'} call</T>
                <Muted>
                  {timeAgo(k.created_at)} · {k.duration_seconds != null ? `${Math.floor(k.duration_seconds / 60)}m ${k.duration_seconds % 60}s` : k.status}
                </Muted>
              </View>
            </Row>
          </Card>
        );
      })}
    </Screen>
  );
}
