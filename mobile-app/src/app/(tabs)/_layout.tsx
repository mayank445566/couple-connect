import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router/js-tabs';
import React from 'react';
import { View, ViewStyle } from 'react-native';
import { useSync } from '../../state/SyncProvider';
import { FONT, useTheme } from '../../state/ThemeProvider';

type IconName = React.ComponentProps<typeof Ionicons>['name'];

export default function TabsLayout() {
  const { c } = useTheme();
  const { unread } = useSync();
  // Filled icon on a soft pill when the tab is active, outline otherwise
  const icon = (name: string) => ({ focused, color }: { focused: boolean; color: any }) => (
    <View style={{ width: 52, height: 30, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: focused ? c.soft : 'transparent' }}>
      <Ionicons name={(focused ? name : `${name}-outline`) as IconName} color={color} size={22} />
    </View>
  );
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: c.primary,
        tabBarInactiveTintColor: c.muted,
        tabBarLabelStyle: { fontFamily: FONT.semibold, fontSize: 11, marginTop: 2 },
        tabBarStyle: { backgroundColor: c.surface, borderTopWidth: 0, height: 72, paddingTop: 8, boxShadow: c.dark ? `0px -1px 0px ${c.border}` : '0px -6px 24px rgba(120, 30, 60, 0.07)' } as ViewStyle,
        tabBarBadgeStyle: { backgroundColor: c.primary, fontFamily: FONT.bold, fontSize: 10 },
        sceneStyle: { backgroundColor: c.background },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: icon('home') }} />
      <Tabs.Screen name="chat" options={{ title: 'Chat', tabBarIcon: icon('chatbubbles'), tabBarBadge: unread > 0 ? (unread > 99 ? '99+' : unread) : undefined }} />
      <Tabs.Screen name="games" options={{ title: 'Games', tabBarIcon: icon('game-controller') }} />
      <Tabs.Screen name="distance" options={{ title: 'Together', tabBarIcon: icon('globe') }} />
      <Tabs.Screen name="us" options={{ title: 'Us', tabBarIcon: icon('heart') }} />
    </Tabs>
  );
}
