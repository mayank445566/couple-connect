import { useFonts } from 'expo-font';
import { Fraunces_600SemiBold } from '@expo-google-fonts/fraunces/600SemiBold';
import { Fraunces_600SemiBold_Italic } from '@expo-google-fonts/fraunces/600SemiBold_Italic';
import { PlusJakartaSans_400Regular } from '@expo-google-fonts/plus-jakarta-sans/400Regular';
import { PlusJakartaSans_500Medium } from '@expo-google-fonts/plus-jakarta-sans/500Medium';
import { PlusJakartaSans_600SemiBold } from '@expo-google-fonts/plus-jakarta-sans/600SemiBold';
import { PlusJakartaSans_700Bold } from '@expo-google-fonts/plus-jakarta-sans/700Bold';
import { PlusJakartaSans_800ExtraBold } from '@expo-google-fonts/plus-jakarta-sans/800ExtraBold';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import React, { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { Background } from '../components/Background';
import { AuthProvider, useAuth } from '../state/AuthProvider';
import { SyncProvider } from '../state/SyncProvider';
import { ThemeProvider, useTheme } from '../state/ThemeProvider';

/** Where a user belongs right now, based on their account state. */
function homeFor(me: ReturnType<typeof useAuth>['me']): string {
  if (!me) return '/welcome';
  if (!me.email_verified && !me.phone_verified) return '/verify';
  if (!me.paired) return '/pair';
  return '/';
}

const AUTH_GROUP = '(auth)';
const PRE_PAIR = ['pair', 'scan', 'settings', 'report'];

function Guard() {
  const { ready, me } = useAuth();
  const segments = useSegments() as string[];
  const router = useRouter();

  useEffect(() => {
    if (!ready) return;
    const first = segments[0] ?? '';
    const inAuth = first === AUTH_GROUP;
    if (!me) {
      if (!inAuth) router.replace('/welcome');
      return;
    }
    const target = homeFor(me);
    if (target === '/verify' && first !== 'verify' && first !== 'settings') router.replace('/verify');
    else if (target === '/pair' && !PRE_PAIR.includes(first)) router.replace('/pair');
    else if (target === '/' && (inAuth || first === 'verify' || first === 'pair' || first === 'scan')) router.replace('/');
  }, [ready, me, segments, router]);

  return null;
}

function Shell() {
  const { ready } = useAuth();
  const { c } = useTheme();
  // Bundled fonts load from the app package in a few milliseconds; if one fails the system font is used
  const [fontsLoaded, fontError] = useFonts({
    Fraunces_600SemiBold,
    Fraunces_600SemiBold_Italic,
    PlusJakartaSans_400Regular,
    PlusJakartaSans_500Medium,
    PlusJakartaSans_600SemiBold,
    PlusJakartaSans_700Bold,
    PlusJakartaSans_800ExtraBold,
  });
  if (!ready || (!fontsLoaded && !fontError)) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: c.background }}>
        <ActivityIndicator color={c.primary} size="large" />
      </View>
    );
  }
  return (
    <>
      <StatusBar style={c.dark ? 'light' : 'dark'} />
      <Guard />
      <Background />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: c.background }, animation: 'slide_from_right' }} />
    </>
  );
}

function Themed() {
  const { me } = useAuth();
  return (
    <ThemeProvider signedIn={!!me}>
      <SyncProvider>
        <Shell />
      </SyncProvider>
    </ThemeProvider>
  );
}

export default function RootLayout() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <AuthProvider>
          <Themed />
        </AuthProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
