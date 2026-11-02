import { router } from 'expo-router';
import React, { useEffect, useState } from 'react';
import { View } from 'react-native';
import { preview } from '../components/chat/MessageBubble';
import { Card, Empty, ErrorBox, Field, Header, Muted, Pill, Row, Screen, SkeletonList, T, timeAgo } from '../components/ui';
import { api, Message } from '../lib/api';

type Tab = 'search' | 'starred' | 'pinned';

export default function ChatSearch() {
  const [tab, setTab] = useState<Tab>('search');
  const [q, setQ] = useState('');
  const [items, setItems] = useState<Message[] | null>(null);
  const [err, setErr] = useState<unknown>(null);

  useEffect(() => {
    let live = true;
    setErr(null);
    if (tab === 'search' && q.trim().length < 2) {
      setItems([]);
      return;
    }
    setItems(null);
    const t = setTimeout(async () => {
      try {
        const path = tab === 'search' ? `/messages/search?q=${encodeURIComponent(q.trim())}` : `/messages/${tab}`;
        const r = await api<Message[] | { messages: Message[] }>('GET', path);
        if (live) setItems(Array.isArray(r) ? r : r.messages);
      } catch (e) {
        if (live) setErr(e);
      }
    }, tab === 'search' ? 350 : 0);
    return () => {
      live = false;
      clearTimeout(t);
    };
  }, [tab, q]);

  return (
    <Screen>
      <Header title="Messages" onBack={() => router.back()} />
      <Row gap={8}>
        <Pill label="Search" icon="search" active={tab === 'search'} onPress={() => setTab('search')} />
        <Pill label="Starred" icon="star-outline" active={tab === 'starred'} onPress={() => setTab('starred')} />
        <Pill label="Pinned" icon="pin-outline" active={tab === 'pinned'} onPress={() => setTab('pinned')} />
      </Row>
      {tab === 'search' ? <Field value={q} onChangeText={setQ} placeholder="Search your chat" autoFocus /> : null}
      {err ? <ErrorBox error={err} /> : null}
      {items === null && !err ? <SkeletonList /> : null}
      {items && items.length === 0 ? (
        <Empty icon={tab === 'starred' ? 'star-outline' : tab === 'pinned' ? 'pin-outline' : 'search'} title={tab === 'search' ? (q.trim().length < 2 ? 'Type at least 2 letters' : 'No matches') : `Nothing ${tab} yet`} text={tab === 'search' ? undefined : 'Long-press a message in the chat to add it here.'} />
      ) : null}
      {items?.map((m) => (
        <Card key={m.id}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T weight="700" size={13}>{m.mine ? 'You' : 'Partner'}</T>
            <Muted>{timeAgo(m.created_at)}</Muted>
          </Row>
          <View>
            <T raw>{preview(m)}</T>
          </View>
        </Card>
      ))}
    </Screen>
  );
}
