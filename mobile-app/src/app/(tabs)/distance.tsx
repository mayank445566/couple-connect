import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React from 'react';
import { View } from 'react-native';
import { Card, Header, Muted, Row, Screen, T } from '../../components/ui';
import { HUB_LISTS, HubList } from '../../lib/constants';
import { useTheme } from '../../state/ThemeProvider';

const ITEMS: { icon: React.ComponentProps<typeof Ionicons>['name']; title: string; text: string; to: string }[] = [
  { icon: 'happy-outline', title: 'Mood', text: 'Share how your day is going', to: '/mood' },
  { icon: 'help-circle-outline', title: 'Daily question', text: 'One question a day, answered together', to: '/daily' },
  { icon: 'mail-outline', title: 'Letters & notes', text: 'Open-when letters, journal, shared notes, wishes', to: '/notes' },
  { icon: 'airplane-outline', title: 'Next meeting', text: 'Set the date you see each other next', to: '/couple-dates' },
  { icon: 'brush-outline', title: 'Draw together', text: 'A live doodle board you both see at once', to: '/draw' },
  { icon: 'call-outline', title: 'Call history', text: 'Voice and video calls with your partner', to: '/calls' },
];

export default function Distance() {
  const { c } = useTheme();
  return (
    <Screen>
      <Header title="Together" />
      <Muted>Little ways to stay close, wherever you both are.</Muted>
      {ITEMS.map((it) => (
        <Tile key={it.to} icon={it.icon} title={it.title} text={it.text} onPress={() => router.push(it.to as any)} color={c.primary} />
      ))}
      <T weight="700" style={{ marginTop: 6 }}>Shared lists</T>
      {(Object.keys(HUB_LISTS) as HubList[]).map((k) => (
        <Tile key={k} icon={HUB_LISTS[k].icon} title={HUB_LISTS[k].title} text={HUB_LISTS[k].empty} onPress={() => router.push(`/hub/${k}`)} color={c.primary} />
      ))}
    </Screen>
  );
}

function Tile({ icon, title, text, onPress, color }: { icon: React.ComponentProps<typeof Ionicons>['name']; title: string; text: string; onPress: () => void; color: string }) {
  return (
    <Card onPress={onPress}>
      <Row>
        <View style={{ width: 42, height: 42, borderRadius: 14, backgroundColor: color + '22', alignItems: 'center', justifyContent: 'center' }}>
          <Ionicons name={icon} size={22} color={color} />
        </View>
        <View style={{ flex: 1 }}>
          <T weight="700">{title}</T>
          <Muted>{text}</Muted>
        </View>
        <Ionicons name="chevron-forward" size={18} color={color + '88'} />
      </Row>
    </Card>
  );
}
