import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import React from 'react';
import { View } from 'react-native';
import { Button, Hero, Muted, Screen, T } from '../../components/ui';
import { useTheme } from '../../state/ThemeProvider';

export default function Welcome() {
  const { c } = useTheme();
  return (
    <Screen>
      <View style={{ height: 24 }} />
      <Hero style={{ alignItems: 'center', paddingVertical: 36 }}>
        <Ionicons name="heart" size={56} color="#fff" />
        <T size={30} weight="800" color="#fff" center>Couple Connect</T>
        <T color="#ffffffDD" center>A private space for just the two of you.</T>
      </Hero>
      <View style={{ gap: 10, marginTop: 8 }}>
        {[
          ['chatbubbles-outline', 'Private chat with photos, voice notes and reactions'],
          ['game-controller-outline', '20 free games to play together'],
          ['globe-outline', 'Long-distance tools: moods, taps, countdowns, letters'],
          ['lock-closed-outline', 'Only your partner can see what you share'],
        ].map(([icon, text]) => (
          <View key={text} style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
            <Ionicons name={icon as any} size={22} color={c.primary} />
            <T style={{ flex: 1 }}>{text}</T>
          </View>
        ))}
      </View>
      <View style={{ gap: 10, marginTop: 16 }}>
        <Button title="Create account" onPress={() => router.push('/register')} />
        <Button title="Sign in with email" variant="secondary" onPress={() => router.push('/login')} />
        <Button title="Use my phone number" variant="ghost" icon="call-outline" onPress={() => router.push('/phone')} />
      </View>
      <Muted center>For adults 18+. By continuing you agree to the Terms and Privacy Policy.</Muted>
    </Screen>
  );
}
