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
import { tr, t, useLang } from '../lib/i18n';
import { FONT, shade, useTheme } from '../state/ThemeProvider';

export type IconName = React.ComponentProps<typeof Ionicons>['name'];

/** Soft tinted wash at the top of a screen, so backgrounds are never flat. */
export function Glow({ height = 440 }: { height?: number }) {
  const { c } = useTheme();
  return <LinearGradient pointerEvents="none" colors={[c.glow, c.background.startsWith('#') && c.background.length === 7 ? c.background + '00' : 'transparent']} start={{ x: 0.5, y: 0 }} end={{ x: 0.5, y: 1 }} style={{ position: 'absolute', top: 0, left: 0, right: 0, height }} />;
}

export function Screen({ children, scroll = true, padded = true, style, edges, refreshing, onRefresh }: { children: React.ReactNode; scroll?: boolean; padded?: boolean; style?: StyleProp<ViewStyle>; edges?: ('top' | 'bottom')[]; refreshing?: boolean; onRefresh?: () => void }) {
  const { c } = useTheme();
  const inner = padded ? { padding: 18, gap: 16 } : undefined;
  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: c.background }} edges={edges ?? ['top']}>
      <Glow />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {scroll ? (
          <ScrollView contentContainerStyle={[inner, { paddingBottom: 48 }, style]} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false} refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={c.primary} colors={[c.primary]} /> : undefined}>
            {children}
          </ScrollView>
        ) : (
          <View style={[{ flex: 1 }, inner, style]}>{children}</View>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const FAMILY: Record<string, string> = { '100': FONT.regular, '200': FONT.regular, '300': FONT.regular, '400': FONT.regular, normal: FONT.regular, '500': FONT.medium, '600': FONT.semibold, '700': FONT.bold, bold: FONT.bold, '800': FONT.extrabold, '900': FONT.extrabold };

/**
 * Text. UI strings are translated here, so every screen follows the app language.
 * Pass `raw` for things people wrote (messages, answers, names) so they are never altered.
 */
export function T({ children, size = 15, weight = '400', color, style, numberOfLines, center, serif, raw }: { children: React.ReactNode; size?: number; weight?: TextStyle['fontWeight']; color?: string; style?: StyleProp<TextStyle>; numberOfLines?: number; center?: boolean; serif?: boolean; raw?: boolean }) {
  const { c } = useTheme();
  useLang();
  if (!raw) children = tr(children);
  const italic = StyleSheet.flatten(style)?.fontStyle === 'italic';
  const family = serif ? (italic ? FONT.serifItalic : FONT.serif) : FAMILY[String(weight)] ?? FONT.regular;
  return (
    <Text numberOfLines={numberOfLines} style={[{ color: color ?? c.text, fontSize: size * c.fontScale, fontFamily: family, textAlign: center ? 'center' : undefined, letterSpacing: serif ? -0.3 : size >= 20 ? -0.2 : 0 }, style]}>
      {children}
    </Text>
  );
}

export function H1({ children }: { children: React.ReactNode }) {
  return <T size={30} serif>{children}</T>;
}

export function Muted({ children, size = 13, center, numberOfLines, raw }: { children: React.ReactNode; size?: number; center?: boolean; numberOfLines?: number; raw?: boolean }) {
  const { c } = useTheme();
  return <T size={size} color={c.muted} center={center} numberOfLines={numberOfLines} raw={raw}>{children}</T>;
}

/** Small uppercase label above a group of cards. */
export function SectionTitle({ children, right }: { children: React.ReactNode; right?: React.ReactNode }) {
  const { c } = useTheme();
  return (
    <Row style={{ justifyContent: 'space-between', marginTop: 6 }}>
      <T size={12} weight="700" color={c.muted} style={{ letterSpacing: 1.2, textTransform: 'uppercase' }}>{children}</T>
      {right}
    </Row>
  );
}

export function cardStyle(c: ReturnType<typeof useTheme>['c']): ViewStyle {
  return c.shadow
    ? ({ backgroundColor: c.surface, borderRadius: 24, boxShadow: c.shadow } as ViewStyle)
    : { backgroundColor: c.surface, borderRadius: 24, borderWidth: StyleSheet.hairlineWidth, borderColor: c.border };
}

export function Card({ children, style, onPress }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void }) {
  const { c } = useTheme();
  const body = <View style={[cardStyle(c), { padding: 18, gap: 8 }, style, onPress ? { flex: undefined, flexBasis: undefined, flexShrink: undefined, minWidth: undefined, width: undefined, flexGrow: 1 } : null]}>{children}</View>;
  // When pressable, size/flex props belong on the outer Pressable so cards still share a row
  const f = StyleSheet.flatten(style) ?? {};
  const outer: ViewStyle = { flex: f.flex, flexGrow: f.flexGrow, flexBasis: f.flexBasis, flexShrink: f.flexShrink, minWidth: f.minWidth, width: f.width, alignSelf: f.alignSelf };
  return onPress ? (
    <Pressable onPress={onPress} style={({ pressed }) => [outer, { opacity: pressed ? 0.92 : 1, transform: [{ scale: pressed ? 0.985 : 1 }] }]}>
      {body}
    </Pressable>
  ) : (
    body
  );
}

/** Icon in a soft tinted circle, or a gradient circle when `filled`. */
export function IconBubble({ name, size = 44, filled, color }: { name: IconName; size?: number; filled?: boolean; color?: string }) {
  const { c } = useTheme();
  if (filled) {
    return (
      <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ width: size, height: size, borderRadius: size / 2.6, alignItems: 'center', justifyContent: 'center' }}>
        <Ionicons name={name} size={size * 0.5} color="#fff" />
      </LinearGradient>
    );
  }
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2.6, backgroundColor: color ? color + '22' : c.soft, alignItems: 'center', justifyContent: 'center' }}>
      <Ionicons name={name} size={size * 0.5} color={color ?? c.primary} />
    </View>
  );
}

export function Button({ title, onPress, variant = 'primary', loading, disabled, icon, style }: { title: string; onPress: () => void; variant?: 'primary' | 'secondary' | 'ghost' | 'danger'; loading?: boolean; disabled?: boolean; icon?: IconName; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const fg = variant === 'primary' || variant === 'danger' ? c.onPrimary : variant === 'secondary' ? c.text : c.primary;
  const inner = (
    <>
      {loading ? <ActivityIndicator color={fg} /> : icon ? <Ionicons name={icon} size={18} color={fg} /> : null}
      <T weight="700" color={fg} size={15.5}>{title}</T>
    </>
  );
  const box: ViewStyle = { minHeight: 54, borderRadius: 999, paddingHorizontal: 22, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: 8 };
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [{ opacity: disabled ? 0.5 : 1, transform: [{ scale: pressed ? 0.98 : 1 }] }, style]}
    >
      {variant === 'primary' ? (
        <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[box, { boxShadow: c.dark ? undefined : `0px 8px 20px ${c.primary}40` } as ViewStyle]}>
          {inner}
        </LinearGradient>
      ) : (
        <View style={[box, { backgroundColor: variant === 'danger' ? c.danger : variant === 'secondary' ? c.surface : 'transparent', borderWidth: variant === 'secondary' ? 1 : 0, borderColor: c.border }]}>{inner}</View>
      )}
    </Pressable>
  );
}

export function Field({ label, error, ...props }: TextInputProps & { label?: string; error?: string }) {
  const { c } = useTheme();
  useLang();
  if (props.placeholder) props = { ...props, placeholder: t(props.placeholder) };
  const [focus, setFocus] = React.useState(false);
  return (
    <View style={{ gap: 6 }}>
      {label ? <T size={13} weight="600" color={c.muted}>{label}</T> : null}
      <TextInput
        placeholderTextColor={c.muted}
        {...props}
        onFocus={(e) => (setFocus(true), props.onFocus?.(e))}
        onBlur={(e) => (setFocus(false), props.onBlur?.(e))}
        style={[{ backgroundColor: c.surface, color: c.text, borderRadius: 16, paddingHorizontal: 16, paddingVertical: 14, fontSize: 16 * c.fontScale, fontFamily: FONT.medium, borderWidth: 1.5, borderColor: error ? c.danger : focus ? c.primary : c.border }, props.style]}
      />
      {error ? <T size={12} color={c.danger}>{error}</T> : null}
    </View>
  );
}

export function Row({ children, style, gap = 10 }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; gap?: number }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap }, style]}>{children}</View>;
}

export function Avatar({ url, name, size = 44, ring }: { url?: string | null; name?: string; size?: number; ring?: boolean }) {
  const { c } = useTheme();
  const inner = url ? (
    <Image source={authedSource(url)} style={{ width: size, height: size, borderRadius: size / 2 }} contentFit="cover" />
  ) : (
    <LinearGradient colors={[shade(c.grad[0], c.dark ? -0.1 : 0.55), shade(c.grad[c.grad.length - 1], c.dark ? -0.2 : 0.45)]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ width: size, height: size, borderRadius: size / 2, alignItems: 'center', justifyContent: 'center' }}>
      <T serif color={c.dark ? '#fff' : c.primary} size={size * 0.42}>{(name ?? '?').slice(0, 1).toUpperCase()}</T>
    </LinearGradient>
  );
  if (!ring) return inner;
  return (
    <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ padding: 2.5, borderRadius: size }}>
      <View style={{ padding: 2, borderRadius: size, backgroundColor: c.background }}>{inner}</View>
    </LinearGradient>
  );
}

export function Hero({ children, style }: { children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  return (
    <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[{ borderRadius: 28, padding: 22, gap: 6, overflow: 'hidden', boxShadow: c.dark ? undefined : `0px 14px 30px ${c.primary}38` } as ViewStyle, style]}>
      {/* Two soft light circles give the gradient some depth */}
      <View pointerEvents="none" style={{ position: 'absolute', width: 220, height: 220, borderRadius: 110, backgroundColor: '#FFFFFF14', top: -90, right: -60 }} />
      <View pointerEvents="none" style={{ position: 'absolute', width: 140, height: 140, borderRadius: 70, backgroundColor: '#FFFFFF10', bottom: -70, left: -30 }} />
      {children}
    </LinearGradient>
  );
}

export function Pill({ label, active, onPress, icon }: { label: string; active?: boolean; onPress?: () => void; icon?: IconName }) {
  const { c } = useTheme();
  const content = (
    <>
      {icon ? <Ionicons name={icon} size={14} color={active ? c.onPrimary : c.text} /> : null}
      <T size={13} weight="700" color={active ? c.onPrimary : c.text}>{label}</T>
    </>
  );
  const box: ViewStyle = { paddingHorizontal: 16, paddingVertical: 9, borderRadius: 999, flexDirection: 'row', gap: 6, alignItems: 'center' };
  return (
    <Pressable onPress={onPress}>
      {active ? (
        <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={box}>{content}</LinearGradient>
      ) : (
        <View style={[box, { backgroundColor: c.surface, borderWidth: 1, borderColor: c.border }]}>{content}</View>
      )}
    </Pressable>
  );
}

export function Empty({ icon = 'heart-outline', title, text, action }: { icon?: IconName; title: string; text?: string; action?: React.ReactNode }) {
  return (
    <View style={{ alignItems: 'center', padding: 28, gap: 10 }}>
      <IconBubble name={icon} size={72} />
      <T serif size={22} center>{title}</T>
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
      <Row style={{ flexShrink: 1 }}>
        {onBack ? (
          <Pressable onPress={onBack} hitSlop={12} accessibilityLabel={t('Back')} style={[cardStyle(c), { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' }]}>
            <Ionicons name="chevron-back" size={22} color={c.text} />
          </Pressable>
        ) : null}
        <T size={28} serif numberOfLines={1} style={{ flexShrink: 1 }}>{title}</T>
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
  if (s < 60) return t('just now');
  if (s < 3600) return t('{n}m ago', { n: Math.floor(s / 60) });
  if (s < 86400) return t('{n}h ago', { n: Math.floor(s / 3600) });
  return new Date(iso).toLocaleDateString();
}

export function clock(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}
