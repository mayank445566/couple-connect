import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useRef } from 'react';
import {
  ActivityIndicator,
  Animated,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ApiError, authedSource } from '../lib/api';
import { useTheme } from '../state/ThemeProvider';

export type IconName = React.ComponentProps<typeof Ionicons>['name'];

export function Screen({ children, scroll = true, padded = true, style, edges, refreshing, onRefresh }: { children: React.ReactNode; scroll?: boolean; padded?: boolean; style?: StyleProp<ViewStyle>; edges?: ('top' | 'bottom')[]; refreshing?: boolean; onRefresh?: () => void }) {
  const { c } = useTheme();
  const inner = padded ? { padding: 16, gap: 14 } : undefined;
  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: c.background }} edges={edges ?? ['top']}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {scroll ? (
          <ScrollView contentContainerStyle={[inner, { paddingBottom: 40 }, style]} keyboardShouldPersistTaps="handled" refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={c.primary} colors={[c.primary]} /> : undefined}>
            {children}
          </ScrollView>
        ) : (
          <View style={[{ flex: 1 }, inner, style]}>{children}</View>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

export function T({ children, size = 15, weight = '400', color, style, numberOfLines, center }: { children: React.ReactNode; size?: number; weight?: TextStyle['fontWeight']; color?: string; style?: StyleProp<TextStyle>; numberOfLines?: number; center?: boolean }) {
  const { c } = useTheme();
  return (
    <Text numberOfLines={numberOfLines} style={[{ color: color ?? c.text, fontSize: size * c.fontScale, fontWeight: weight, textAlign: center ? 'center' : undefined }, style]}>
      {children}
    </Text>
  );
}

export function H1({ children }: { children: React.ReactNode }) {
  return <T size={26} weight="700">{children}</T>;
}

export function Muted({ children, size = 13, center }: { children: React.ReactNode; size?: number; center?: boolean }) {
  const { c } = useTheme();
  return <T size={size} color={c.muted} center={center}>{children}</T>;
}

export function Card({ children, style, onPress }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void }) {
  const { c } = useTheme();
  const body = <View style={[{ backgroundColor: c.surface, borderRadius: 20, padding: 16, gap: 8, borderWidth: StyleSheet.hairlineWidth, borderColor: c.border }, style]}>{children}</View>;
  return onPress ? (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1, transform: [{ scale: pressed ? 0.99 : 1 }] })}>
      {body}
    </Pressable>
  ) : (
    body
  );
}

export function Button({ title, onPress, variant = 'primary', loading, disabled, icon, style }: { title: string; onPress: () => void; variant?: 'primary' | 'secondary' | 'ghost' | 'danger'; loading?: boolean; disabled?: boolean; icon?: IconName; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const bg = variant === 'primary' ? c.primary : variant === 'danger' ? c.danger : variant === 'secondary' ? c.surface : 'transparent';
  const fg = variant === 'primary' || variant === 'danger' ? c.onPrimary : c.primary;
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [
        { backgroundColor: bg, borderRadius: 16, paddingVertical: 14, paddingHorizontal: 18, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 8, opacity: disabled ? 0.5 : pressed ? 0.85 : 1, borderWidth: variant === 'secondary' ? 1 : 0, borderColor: c.border },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={fg} /> : icon ? <Ionicons name={icon} size={18} color={fg} /> : null}
      <T weight="600" color={fg}>{title}</T>
    </Pressable>
  );
}

export function Field({ label, error, ...props }: TextInputProps & { label?: string; error?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ gap: 6 }}>
      {label ? <T size={13} weight="600" color={c.muted}>{label}</T> : null}
      <TextInput
        placeholderTextColor={c.muted}
        {...props}
        style={[{ backgroundColor: c.surface, color: c.text, borderRadius: 14, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16 * c.fontScale, borderWidth: 1, borderColor: error ? c.danger : c.border }, props.style]}
      />
      {error ? <T size={12} color={c.danger}>{error}</T> : null}
    </View>
  );
}

export function Row({ children, style, gap = 10 }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; gap?: number }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap }, style]}>{children}</View>;
}

export function Avatar({ url, name, size = 44 }: { url?: string | null; name?: string; size?: number }) {
  const { c } = useTheme();
  if (url) return <Image source={authedSource(url)} style={{ width: size, height: size, borderRadius: size / 2 }} contentFit="cover" />;
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: c.primary + '33', alignItems: 'center', justifyContent: 'center' }}>
      <T weight="700" color={c.primary} size={size * 0.4}>{(name ?? '?').slice(0, 1).toUpperCase()}</T>
    </View>
  );
}

export function Hero({ children, style }: { children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const colors = (c.gradient && c.gradient.length >= 2 ? c.gradient : [c.primary, c.primary + 'AA']) as [string, string, ...string[]];
  return (
    <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[{ borderRadius: 24, padding: 20, gap: 6 }, style]}>
      {children}
    </LinearGradient>
  );
}

export function Pill({ label, active, onPress, icon }: { label: string; active?: boolean; onPress?: () => void; icon?: IconName }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={onPress} style={{ paddingHorizontal: 14, paddingVertical: 8, borderRadius: 999, backgroundColor: active ? c.primary : c.surface, borderWidth: 1, borderColor: active ? c.primary : c.border, flexDirection: 'row', gap: 6, alignItems: 'center' }}>
      {icon ? <Ionicons name={icon} size={14} color={active ? c.onPrimary : c.text} /> : null}
      <T size={13} weight="600" color={active ? c.onPrimary : c.text}>{label}</T>
    </Pressable>
  );
}

export function Empty({ icon = 'heart-outline', title, text, action }: { icon?: IconName; title: string; text?: string; action?: React.ReactNode }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', padding: 28, gap: 8 }}>
      <Ionicons name={icon} size={42} color={c.primary} />
      <T weight="700" size={17} center>{title}</T>
      {text ? <Muted center>{text}</Muted> : null}
      {action}
    </View>
  );
}

export function ErrorBox({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const { c } = useTheme();
  const msg = errorText(error);
  return (
    <Card style={{ borderColor: c.danger }}>
      <Row>
        <Ionicons name="alert-circle" size={20} color={c.danger} />
        <T style={{ flex: 1 }}>{msg}</T>
      </Row>
      {onRetry ? <Button title="Try again" variant="ghost" onPress={onRetry} /> : null}
    </Card>
  );
}

/** Pulsing placeholder for loading states. */
export function Skeleton({ height = 18, width = '100%', radius = 10 }: { height?: number; width?: number | `${number}%`; radius?: number }) {
  const { c } = useTheme();
  const v = useRef(new Animated.Value(0.4)).current;
  useEffect(() => {
    const loop = Animated.loop(Animated.sequence([Animated.timing(v, { toValue: 1, duration: 700, useNativeDriver: true }), Animated.timing(v, { toValue: 0.4, duration: 700, useNativeDriver: true })]));
    loop.start();
    return () => loop.stop();
  }, [v]);
  return <Animated.View style={{ height, width, borderRadius: radius, backgroundColor: c.border, opacity: v }} />;
}

export function SkeletonList({ rows = 4 }: { rows?: number }) {
  return (
    <View style={{ gap: 12 }}>
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} height={64} radius={18} />
      ))}
    </View>
  );
}

export function Header({ title, right, onBack }: { title: string; right?: React.ReactNode; onBack?: () => void }) {
  const { c } = useTheme();
  return (
    <Row style={{ justifyContent: 'space-between', paddingVertical: 4 }}>
      <Row>
        {onBack ? (
          <Pressable onPress={onBack} hitSlop={12} accessibilityLabel="Back">
            <Ionicons name="chevron-back" size={26} color={c.text} />
          </Pressable>
        ) : null}
        <T size={22} weight="700">{title}</T>
      </Row>
      {right}
    </Row>
  );
}

export function fieldErrors(e: unknown): Record<string, string> {
  return e instanceof ApiError ? e.fields : {};
}

export function errorText(e: unknown): string {
  if (e instanceof ApiError || e instanceof Error) return e.message || 'Something went wrong.';
  if (e && typeof e === 'object' && typeof (e as any).message === 'string') return (e as any).message;
  return 'Something went wrong.';
}

export function timeAgo(iso: string | null | undefined): string {
  if (!iso) return '';
  const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return 'just now';
  if (s < 3600) return `${Math.floor(s / 60)}m ago`;
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
  return new Date(iso).toLocaleDateString();
}

export function clock(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}
