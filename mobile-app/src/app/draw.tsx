import { router } from 'expo-router';
import React, { useCallback, useRef, useState } from 'react';
import { PanResponder, Pressable, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { Button, Card, Header, Muted, Row, Screen, T } from '../components/ui';
import { useSync, useSyncEvent } from '../state/SyncProvider';
import { useTheme } from '../state/ThemeProvider';

type Stroke = { id: string; color: string; width: number; pts: number[] };
const COLORS = ['#E8456B', '#3E63DD', '#30A46C', '#F5A524', '#8E4EC6', '#1A1A1A'];
const toPath = (pts: number[]) => pts.reduce((d, v, i) => (i % 2 === 0 ? d + (i === 0 ? 'M' : 'L') + v.toFixed(1) : d + ',' + v.toFixed(1) + ' '), '');

/**
 * Live drawing board. Strokes travel through the relay straight to your partner's screen
 * and are not stored anywhere: when you both leave, the drawing is gone.
 */
export default function Draw() {
  const { c } = useTheme();
  const { relay } = useSync();
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const [color, setColor] = useState(COLORS[0]);
  const current = useRef<Stroke | null>(null);
  const sentUpTo = useRef(0);
  const colorRef = useRef(color);
  colorRef.current = color;
  const size = useRef({ w: 1, h: 1 });

  // Coordinates are sent as 0..1 so drawings line up on screens of any size
  const send = useCallback(
    (final: boolean) => {
      const s = current.current;
      if (!s) return;
      const pts = s.pts.slice(sentUpTo.current > 2 ? sentUpTo.current - 2 : 0).map((v, i) => +(v / (i % 2 === 0 ? size.current.w : size.current.h)).toFixed(4));
      if (pts.length < 2) return;
      relay.sendPeer({ kind: 'draw', op: 'seg', id: s.id, color: s.color, width: s.width, pts, final });
      sentUpTo.current = s.pts.length;
    },
    [relay],
  );

  const pan = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: (e) => {
        const { locationX: x, locationY: y } = e.nativeEvent;
        current.current = { id: Math.random().toString(36).slice(2), color: colorRef.current, width: 4, pts: [x, y] };
        sentUpTo.current = 0;
        setStrokes((s) => [...s, current.current!]);
      },
      onPanResponderMove: (e) => {
        const s = current.current;
        if (!s) return;
        s.pts.push(e.nativeEvent.locationX, e.nativeEvent.locationY);
        setStrokes((all) => all.map((x) => (x.id === s.id ? { ...s, pts: [...s.pts] } : x)));
        if (s.pts.length - sentUpTo.current >= 8) sendRef.current(false);
      },
      onPanResponderRelease: () => {
        sendRef.current(true);
        current.current = null;
      },
    }),
  ).current;
  const sendRef = useRef(send);
  sendRef.current = send;

  useSyncEvent('peer.draw', (e) => {
    const d = e.payload;
    if (d.op === 'clear') return setStrokes([]);
    if (d.op !== 'seg' || !Array.isArray(d.pts)) return;
    const pts = (d.pts as number[]).map((v, i) => v * (i % 2 === 0 ? size.current.w : size.current.h));
    setStrokes((all) => {
      const i = all.findIndex((x) => x.id === d.id);
      if (i < 0) return [...all, { id: String(d.id), color: String(d.color), width: Number(d.width) || 4, pts }];
      const next = [...all];
      next[i] = { ...next[i], pts: [...next[i].pts, ...pts] };
      return next;
    });
  });

  const clear = () => {
    setStrokes([]);
    relay.sendPeer({ kind: 'draw', op: 'clear' });
  };

  return (
    <Screen scroll={false}>
      <Header title="Draw together" onBack={() => router.back()} right={<Button title="Clear" variant="ghost" onPress={clear} />} />
      {!relay.connected ? (
        <Card>
          <T weight="600">Live drawing isn't available right now</T>
          <Muted>It needs the real-time server, which isn't set up or can't be reached. You can still doodle here, but your partner won't see it.</Muted>
        </Card>
      ) : (
        <Muted>{relay.partnerLive ? 'Your partner is online. Draw and they see it live.' : 'Your partner isn’t in the app right now. Ask them to open Draw together.'}</Muted>
      )}
      <View
        style={{ flex: 1, backgroundColor: '#fff', borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: c.border }}
        onLayout={(e) => (size.current = { w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height })}
        {...pan.panHandlers}
      >
        <Svg width="100%" height="100%">
          {strokes.map((s) => (
            <Path key={s.id} d={toPath(s.pts)} stroke={s.color} strokeWidth={s.width} strokeLinecap="round" strokeLinejoin="round" fill="none" />
          ))}
        </Svg>
      </View>
      <Row style={{ justifyContent: 'center' }} gap={12}>
        {COLORS.map((col) => (
          <Pressable key={col} onPress={() => setColor(col)} accessibilityLabel={`Colour ${col}`} style={{ width: 34, height: 34, borderRadius: 17, backgroundColor: col, borderWidth: color === col ? 3 : 0, borderColor: c.text }} />
        ))}
      </Row>
    </Screen>
  );
}
