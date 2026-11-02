import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Card, ErrorBox, Header, Muted, Pill, Row, Screen, T } from '../components/ui';
import { ApiError } from '../lib/api';
import { useTheme } from '../state/ThemeProvider';

const SWATCHES = ['#E8456B', '#FF7A59', '#F5A524', '#30A46C', '#12A594', '#3E63DD', '#8E4EC6', '#D6409F', '#1A1A1A', '#6B7280'];
const SCALES = [0.9, 1, 1.15, 1.3];

export default function Themes() {
  const { c, themes, prefs, apply } = useTheme();
  const [err, setErr] = useState<unknown>(null);

  const run = async (patch: Record<string, unknown>) => {
    setErr(null);
    try {
      await apply(patch);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'premium_required') router.push('/premium');
      else setErr(e);
    }
  };

  return (
    <Screen>
      <Header title="Themes" onBack={() => router.back()} />
      {err ? <ErrorBox error={err} /> : null}

      <T weight="700">Appearance</T>
      <Row gap={8}>
        {(['system', 'light', 'dark'] as const).map((m) => (
          <Pill key={m} label={m[0].toUpperCase() + m.slice(1)} active={(prefs?.color_mode ?? 'system') === m} onPress={() => run({ color_mode: m })} />
        ))}
      </Row>

      <T weight="700">Theme</T>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
        {themes.map((t) => {
          const g = (t.tokens.gradient && t.tokens.gradient.length >= 2 ? t.tokens.gradient : [t.tokens.primary, t.tokens.background]) as [string, string, ...string[]];
          return (
            <Pressable key={t.slug} onPress={() => (t.locked ? router.push('/premium') : run({ theme_slug: t.slug }))} style={{ width: '47%', flexGrow: 1 }} accessibilityLabel={`${t.name} theme${t.locked ? ', locked' : ''}`}>
              <View style={{ borderRadius: 20, overflow: 'hidden', borderWidth: t.active ? 3 : 1, borderColor: t.active ? c.primary : c.border }}>
                <LinearGradient colors={g} style={{ height: 70, padding: 10, justifyContent: 'flex-end' }}>
                  <View style={{ alignSelf: 'flex-end', backgroundColor: t.tokens.bubble_mine, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 4 }}>
                    <T size={11} color="#fff">Hi ❤️</T>
                  </View>
                </LinearGradient>
                <Row style={{ padding: 10, backgroundColor: t.tokens.surface, justifyContent: 'space-between' }}>
                  <T size={13} weight="600" color={t.tokens.text}>{t.name}</T>
                  {t.locked ? <Ionicons name="lock-closed" size={14} color={t.tokens.text} /> : t.active ? <Ionicons name="checkmark-circle" size={16} color={t.tokens.primary} /> : null}
                </Row>
              </View>
            </Pressable>
          );
        })}
      </View>

      <T weight="700">Text size</T>
      <Row gap={8}>
        {SCALES.map((s) => (
          <Pill key={s} label={s === 1 ? 'Default' : `${Math.round(s * 100)}%`} active={Math.abs((prefs?.font_scale ?? 1) - s) < 0.01} onPress={() => run({ font_scale: s })} />
        ))}
      </Row>

      <Card>
        <Row style={{ justifyContent: 'space-between' }}>
          <T weight="700">Custom colours</T>
          <Muted>Premium</Muted>
        </Row>
        {(['accent', 'bubble_mine', 'bubble_theirs'] as const).map((k) => (
          <View key={k} style={{ gap: 6 }}>
            <Muted>{k === 'accent' ? 'Accent' : k === 'bubble_mine' ? 'My bubbles' : "Partner's bubbles"}</Muted>
            <Row gap={8} style={{ flexWrap: 'wrap' }}>
              {SWATCHES.map((s) => (
                <Pressable key={s} onPress={() => run({ [k]: s })} accessibilityLabel={`${k} ${s}`} style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: s, borderWidth: prefs?.[k] === s ? 3 : 0, borderColor: c.text }} />
              ))}
            </Row>
          </View>
        ))}
        {prefs?.accent || prefs?.bubble_mine || prefs?.bubble_theirs ? <Pill label="Reset to theme colours" icon="refresh" onPress={() => run({ reset_colors: true })} /> : null}
      </Card>
    </Screen>
  );
}
