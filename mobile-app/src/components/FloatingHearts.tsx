import React, { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Platform, StyleSheet, Text, View } from 'react-native';

type Burst = { id: number; emoji: string; x: number; count: number };

/**
 * A burst of emoji that float up and fade, e.g. after sending a love tap.
 * Call the returned `fire(emoji, x)` with the horizontal start position (0..1 of the width).
 */
export function useFloatingHearts() {
  const [bursts, setBursts] = useState<Burst[]>([]);
  const seq = useRef(0);
  const fire = (emoji: string, x = 0.5, count = 9) => {
    const id = ++seq.current;
    setBursts((b) => [...b, { id, emoji, x, count }]);
    setTimeout(() => setBursts((b) => b.filter((v) => v.id !== id)), 2200);
  };
  const layer = (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      {bursts.map((b) => (
        <BurstView key={b.id} burst={b} />
      ))}
    </View>
  );
  return { fire, layer };
}

function BurstView({ burst }: { burst: Burst }) {
  const parts = useRef(
    Array.from({ length: burst.count }, (_, i) => ({
      v: new Animated.Value(0),
      dx: (Math.random() - 0.5) * 160,
      rise: 260 + Math.random() * 220,
      delay: i * 70,
      size: 22 + Math.random() * 18,
      spin: (Math.random() - 0.5) * 50,
    })),
  ).current;

  useEffect(() => {
    Animated.parallel(
      parts.map((p) => Animated.timing(p.v, { toValue: 1, duration: 1500, delay: p.delay, easing: Easing.out(Easing.cubic), useNativeDriver: Platform.OS !== 'web' })),
    ).start();
  }, [parts]);

  return (
    <View style={{ position: 'absolute', left: `${burst.x * 100}%`, bottom: 120 }}>
      {parts.map((p, i) => (
        <Animated.View
          key={i}
          style={{
            position: 'absolute',
            opacity: p.v.interpolate({ inputRange: [0, 0.1, 0.7, 1], outputRange: [0, 1, 1, 0] }),
            transform: [
              { translateX: p.v.interpolate({ inputRange: [0, 1], outputRange: [0, p.dx] }) },
              { translateY: p.v.interpolate({ inputRange: [0, 1], outputRange: [0, -p.rise] }) },
              { scale: p.v.interpolate({ inputRange: [0, 0.2, 1], outputRange: [0.3, 1.15, 0.9] }) },
              { rotate: p.v.interpolate({ inputRange: [0, 1], outputRange: ['0deg', `${p.spin}deg`] }) },
            ],
          }}
        >
          <Text style={{ fontSize: p.size }}>{burst.emoji}</Text>
        </Animated.View>
      ))}
    </View>
  );
}

/** A gentle repeating heartbeat scale, for the heart between the two avatars. */
export function useHeartbeat() {
  const v = useRef(new Animated.Value(1)).current;
  useEffect(() => {
    const native = Platform.OS !== 'web';
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(v, { toValue: 1.18, duration: 160, useNativeDriver: native }),
        Animated.timing(v, { toValue: 1, duration: 160, useNativeDriver: native }),
        Animated.timing(v, { toValue: 1.12, duration: 160, useNativeDriver: native }),
        Animated.timing(v, { toValue: 1, duration: 900, useNativeDriver: native }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [v]);
  return v;
}
