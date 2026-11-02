import { LinearGradient } from 'expo-linear-gradient';
import React, { useEffect, useMemo, useRef } from 'react';
import { Animated, Easing, Platform, StyleSheet, Text, View } from 'react-native';
import { T } from './ui';

const native = Platform.OS !== 'web';

/** Card colours per intensity level. Deeper and warmer as the level rises. */
const LEVEL_GRADS: Record<string, [string, string, string]> = {
  flirty: ['#FF6FA3', '#F0437E', '#B92D6B'],
  romantic: ['#FF8A8A', '#E6517A', '#9E2F64'],
  bold: ['#FF8C5A', '#E8455A', '#9C1F4A'],
  intimate: ['#B05CE0', '#7B2FB8', '#3E1466'],
  spicy: ['#FF4D4D', '#C3123F', '#5E0A2A'],
};
const WILD_A: [string, string, string] = ['#2A0A2E', '#8E1652', '#E8466F'];
const WILD_B: [string, string, string] = ['#3B0D4F', '#C2185B', '#FF7A59'];

/** Picks a picture for the card from its words. Tasteful: symbols, never bodies. */
const ART: [RegExp, string][] = [
  [/kiss|lips|चूम|किस/i, '💋'],
  [/massage|shoulder|back rub|मसाज|मालिश/i, '💆'],
  [/danc|नाच|डांस/i, '💃'],
  [/whisper|ear|फुसफुस|कान/i, '🤫'],
  [/candle|मोमबत्ती|कैंडल/i, '🕯️'],
  [/wine|drink|toast|वाइन|ड्रिंक/i, '🍷'],
  [/bath|shower|नहा|बाथ/i, '🛁'],
  [/blindfold|eyes closed|आंखें बंद|पट्टी/i, '🙈'],
  [/bed|pillow|cuddle|बिस्तर|तकिया|cuddl/i, '🛏️'],
  [/hug|hold|embrace|गले|बाहों/i, '🫂'],
  [/song|music|playlist|गाना|म्यूज़िक/i, '🎶'],
  [/photo|selfie|picture|फ़ोटो|सेल्फ़ी/i, '📸'],
  [/letter|note|write|लिख|चिट्ठी/i, '💌'],
  [/outfit|dress|wear|lingerie|कपड़े|पहन/i, '👗'],
  [/ice|cold|बर्फ़/i, '🧊'],
  [/chocolate|strawberr|feed|dessert|चॉकलेट|खिला/i, '🍓'],
  [/call|video|कॉल/i, '📱'],
  [/star|night|moon|रात|चांद/i, '🌙'],
  [/secret|confess|राज़|कबूल/i, '🔐'],
  [/touch|hand|fingers|छू|हाथ/i, '🤲'],
  [/fantasy|dream|सपन|फैंटेसी/i, '✨'],
];

export function artFor(text: string, fallback = '🔥'): string {
  for (const [re, e] of ART) if (re.test(text)) return e;
  return fallback;
}

type Props = {
  text: string;
  /** Changes for every new card, so the card flips in again. */
  cardKey: string | number;
  kind?: string;
  level?: string | null;
  wild?: boolean;
  caption?: string | null;
};

/**
 * The 18+ prompt card: flips in, glows and drifts little sparks.
 * Wild cards get a slowly shifting gradient and a bigger reveal.
 */
export function PromptCard({ text, cardKey, kind, level, wild, caption }: Props) {
  const flip = useRef(new Animated.Value(0)).current;
  const pulse = useRef(new Animated.Value(0)).current;
  const shift = useRef(new Animated.Value(0)).current;
  const spin = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    flip.setValue(0);
    Animated.timing(flip, { toValue: 1, duration: wild ? 900 : 650, easing: Easing.out(Easing.back(1.2)), useNativeDriver: native }).start();
  }, [cardKey, wild, flip]);

  useEffect(() => {
    const loops = [
      Animated.loop(Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 1400, easing: Easing.inOut(Easing.sin), useNativeDriver: native }),
        Animated.timing(pulse, { toValue: 0, duration: 1400, easing: Easing.inOut(Easing.sin), useNativeDriver: native }),
      ])),
      Animated.loop(Animated.sequence([
        Animated.timing(shift, { toValue: 1, duration: 3200, easing: Easing.inOut(Easing.quad), useNativeDriver: native }),
        Animated.timing(shift, { toValue: 0, duration: 3200, easing: Easing.inOut(Easing.quad), useNativeDriver: native }),
      ])),
      Animated.loop(Animated.timing(spin, { toValue: 1, duration: 9000, easing: Easing.linear, useNativeDriver: native })),
    ];
    loops.forEach((l) => l.start());
    return () => loops.forEach((l) => l.stop());
  }, [pulse, shift, spin]);

  const grad = LEVEL_GRADS[level ?? ''] ?? LEVEL_GRADS.flirty;
  const art = useMemo(() => artFor(text, wild ? '🃏' : kind === 'dare' ? '🔥' : kind === 'truth' ? '💭' : '💞'), [text, wild, kind]);
  const label = wild ? 'WILD CARD' : kind === 'truth' ? 'TRUTH' : kind === 'dare' ? 'DARE' : kind === 'challenge' ? 'CHALLENGE' : null;

  return (
    <Animated.View
      style={{
        borderRadius: 30,
        overflow: 'hidden',
        minHeight: wild ? 380 : 300,
        opacity: flip.interpolate({ inputRange: [0, 0.3, 1], outputRange: [0, 1, 1] }),
        transform: [
          { perspective: 1000 },
          { rotateY: flip.interpolate({ inputRange: [0, 1], outputRange: [wild ? '180deg' : '70deg', '0deg'] }) },
          { scale: flip.interpolate({ inputRange: [0, 1], outputRange: [0.85, 1] }) },
        ],
        boxShadow: `0px 18px 40px ${grad[1]}66`,
      } as any}
    >
      <LinearGradient colors={wild ? WILD_A : grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      {wild ? (
        <Animated.View style={[StyleSheet.absoluteFill, { opacity: shift }]}>
          <LinearGradient colors={WILD_B} start={{ x: 1, y: 0 }} end={{ x: 0, y: 1 }} style={StyleSheet.absoluteFill} />
        </Animated.View>
      ) : null}

      <Sparks count={wild ? 14 : 8} />

      {/* Thin inner frame, like a printed card */}
      <View pointerEvents="none" style={{ position: 'absolute', top: 10, left: 10, right: 10, bottom: 10, borderRadius: 22, borderWidth: 1, borderColor: '#FFFFFF33' }} />

      <View style={{ padding: 26, gap: 14, alignItems: 'center', justifyContent: 'center', flex: 1 }}>
        {label ? (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 14, paddingVertical: 5, borderRadius: 999, backgroundColor: '#FFFFFF26' }}>
            <Text style={{ color: '#fff', fontSize: 11 }}>✦</Text>
            <T color="#fff" size={11.5} weight="800" style={{ letterSpacing: 2 }}>{label}</T>
            <Text style={{ color: '#fff', fontSize: 11 }}>✦</Text>
          </View>
        ) : null}

        <View style={{ width: wild ? 132 : 104, height: wild ? 132 : 104, alignItems: 'center', justifyContent: 'center' }}>
          <Animated.View
            style={{
              position: 'absolute',
              width: '100%',
              height: '100%',
              borderRadius: 999,
              backgroundColor: '#FFFFFF',
              opacity: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.12, 0.28] }),
              transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.9, 1.15] }) }],
            }}
          />
          <Animated.View style={{ position: 'absolute', width: '100%', height: '100%', transform: [{ rotate: spin.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] }) }] }}>
            <Text style={{ position: 'absolute', top: -6, left: '45%', fontSize: 14 }}>✨</Text>
            <Text style={{ position: 'absolute', bottom: -4, right: '18%', fontSize: 12 }}>💗</Text>
            <Text style={{ position: 'absolute', top: '40%', left: -8, fontSize: 11 }}>✦</Text>
          </Animated.View>
          <Text style={{ fontSize: wild ? 64 : 50 }}>{art}</Text>
        </View>

        {caption ? <T color="#ffffffCC" size={13} center>{caption}</T> : null}
        <T raw color="#fff" size={wild ? 23 : 21} weight="700" center style={{ lineHeight: wild ? 32 : 29 }}>{text}</T>
        {level ? <T color="#ffffffB3" size={12} weight="700" style={{ letterSpacing: 1.5, textTransform: 'uppercase' }}>{level}</T> : null}
      </View>
    </Animated.View>
  );
}

/** Small glowing dots and hearts drifting upward inside the card, forever. */
function Sparks({ count }: { count: number }) {
  const parts = useRef(
    Array.from({ length: count }, (_, i) => ({
      v: new Animated.Value(Math.random()),
      left: `${5 + Math.random() * 90}%`,
      size: 6 + Math.random() * 10,
      heart: i % 3 === 0,
      duration: 4000 + Math.random() * 4000,
    })),
  ).current;

  useEffect(() => {
    const loops = parts.map((p) => {
      const start = (p.v as any)._value ?? 0;
      return Animated.loop(
        Animated.sequence([
          Animated.timing(p.v, { toValue: 1, duration: p.duration * (1 - start), easing: Easing.linear, useNativeDriver: native }),
          Animated.timing(p.v, { toValue: 0, duration: 0, useNativeDriver: native }),
        ]),
      );
    });
    loops.forEach((l) => l.start());
    return () => loops.forEach((l) => l.stop());
  }, [parts]);

  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      {parts.map((p, i) => (
        <Animated.View
          key={i}
          style={{
            position: 'absolute',
            bottom: 0,
            left: p.left as any,
            opacity: p.v.interpolate({ inputRange: [0, 0.15, 0.8, 1], outputRange: [0, 0.9, 0.6, 0] }),
            transform: [{ translateY: p.v.interpolate({ inputRange: [0, 1], outputRange: [10, -420] }) }],
          }}
        >
          {p.heart ? (
            <Text style={{ fontSize: p.size + 4, color: '#FFFFFFAA' }}>♥</Text>
          ) : (
            <View style={{ width: p.size / 2, height: p.size / 2, borderRadius: p.size, backgroundColor: '#FFFFFFAA' }} />
          )}
        </Animated.View>
      ))}
    </View>
  );
}
