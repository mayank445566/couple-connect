import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect } from 'react';
import { Animated, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFloatingHearts, useHeartbeat } from '../../components/FloatingHearts';
import { Button, IconBubble, IconName, Muted, T } from '../../components/ui';
import { useTheme } from '../../state/ThemeProvider';

const FEATURES: [IconName, string, string][] = [
  ['chatbubbles', 'Private chat', 'Photos, voice notes, reactions'],
  ['game-controller', '20 free games', 'Play together, near or far'],
  ['globe', 'Long distance', 'Moods, taps and countdowns'],
  ['lock-closed', 'Just you two', 'Only your partner sees it'],
];

export default function Welcome() {
  const { c } = useTheme();
  const beat = useHeartbeat();
  const hearts = useFloatingHearts();

  // A few hearts drift up when the screen opens
  useEffect(() => {
    const t = setTimeout(() => hearts.fire('💗', 0.5, 8), 500);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <View style={{ flex: 1, backgroundColor: c.background }}>
      <ScrollView contentContainerStyle={{ flexGrow: 1 }} showsVerticalScrollIndicator={false}>
        <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ borderBottomLeftRadius: 40, borderBottomRightRadius: 40, overflow: 'hidden' }}>
          <View pointerEvents="none" style={{ position: 'absolute', width: 300, height: 300, borderRadius: 150, backgroundColor: '#FFFFFF14', top: -120, right: -90 }} />
          <View pointerEvents="none" style={{ position: 'absolute', width: 180, height: 180, borderRadius: 90, backgroundColor: '#FFFFFF10', bottom: -60, left: -50 }} />
          <SafeAreaView edges={['top']} style={{ alignItems: 'center', paddingHorizontal: 28, paddingTop: 48, paddingBottom: 44, gap: 10 }}>
            <Animated.View style={{ width: 92, height: 92, borderRadius: 46, backgroundColor: '#FFFFFF2E', alignItems: 'center', justifyContent: 'center', transform: [{ scale: beat }] }}>
              <View style={{ width: 68, height: 68, borderRadius: 34, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' }}>
                <Ionicons name="heart" size={36} color={c.primary} />
              </View>
            </Animated.View>
            <T serif size={40} color="#fff" center style={{ marginTop: 10, lineHeight: 46 }}>Couple Connect</T>
            <T size={16} color="#ffffffE6" center weight="500">A private little world{'\n'}for just the two of you.</T>
          </SafeAreaView>
        </LinearGradient>

        <View style={{ padding: 20, gap: 18, flex: 1 }}>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
            {FEATURES.map(([icon, title, text]) => (
              <View key={title} style={{ flexBasis: '46%', flexGrow: 1, gap: 6, padding: 14, borderRadius: 20, backgroundColor: c.surface, ...(c.shadow ? ({ boxShadow: c.shadow } as object) : { borderWidth: 1, borderColor: c.border }) }}>
                <IconBubble name={icon} size={38} />
                <T weight="700" size={14.5}>{title}</T>
                <Muted size={12.5}>{text}</Muted>
              </View>
            ))}
          </View>
          <View style={{ gap: 10, marginTop: 4 }}>
            <Button title="Create account" onPress={() => router.push('/register')} />
            <Button title="Sign in with email" variant="secondary" onPress={() => router.push('/login')} />
            <Button title="Use my phone number" variant="ghost" icon="call-outline" onPress={() => router.push('/phone')} />
          </View>
          <Muted center size={12}>For adults 18+. By continuing you agree to the Terms and Privacy Policy.</Muted>
        </View>
      </ScrollView>
      {hearts.layer}
    </View>
  );
}
