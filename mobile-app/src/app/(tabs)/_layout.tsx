import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router/js-tabs';
import React from 'react';
import { useSync } from '../../state/SyncProvider';
import { useTheme } from '../../state/ThemeProvider';

export default function TabsLayout() {
  const { c } = useTheme();
  const { unread } = useSync();
  const icon = (name: React.ComponentProps<typeof Ionicons>['name']) => ({ color, size }: { color: any; size: number }) => <Ionicons name={name} color={color} size={size} />;
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: c.primary,
        tabBarInactiveTintColor: c.muted,
        tabBarStyle: { backgroundColor: c.surface, borderTopColor: c.border },
        sceneStyle: { backgroundColor: c.background },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: icon('home-outline') }} />
      <Tabs.Screen name="chat" options={{ title: 'Chat', tabBarIcon: icon('chatbubbles-outline'), tabBarBadge: unread > 0 ? (unread > 99 ? '99+' : unread) : undefined }} />
      <Tabs.Screen name="games" options={{ title: 'Games', tabBarIcon: icon('game-controller-outline') }} />
      <Tabs.Screen name="distance" options={{ title: 'Together', tabBarIcon: icon('globe-outline') }} />
      <Tabs.Screen name="us" options={{ title: 'Us', tabBarIcon: icon('heart-outline') }} />
    </Tabs>
  );
}
