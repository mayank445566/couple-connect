import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Vibration, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Avatar, T } from '../../components/ui';
import { api, Couple } from '../../lib/api';
import { useLoad } from '../../lib/useLoad';
import { createPeer, getMedia, iceCandidate, sessionDescription, switchCamera, Video, webrtcAvailable } from '../../lib/webrtc';
import { useSyncEvent } from '../../state/SyncProvider';

export type Call = { id: number; kind: 'audio' | 'video'; status: 'ringing' | 'active' | 'ended' | 'declined' | 'missed' | 'cancelled'; outgoing: boolean; duration_seconds: number | null; answered_at: string | null };
type Signal = { id: number; kind: 'offer' | 'answer' | 'ice' | 'renegotiate'; payload: any };

const ENDED: Call['status'][] = ['ended', 'declined', 'missed', 'cancelled'];
const endLabel: Record<string, string> = { ended: 'Call ended', declined: 'Call declined', missed: 'No answer', cancelled: 'Call cancelled' };

/**
 * One-to-one WebRTC call. Audio and video go directly between the phones (or through
 * the TURN server); only call state and signalling go through the API.
 */
export default function CallScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const callId = Number(id);
  const couple = useLoad(() => api<Couple>('GET', '/couple'));
  const [call, setCall] = useState<Call | null>(null);
  const [local, setLocal] = useState<any>(null);
  const [remote, setRemote] = useState<any>(null);
  const [connected, setConnected] = useState(false);
  const [muted, setMuted] = useState(false);
  const [camOff, setCamOff] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const [secs, setSecs] = useState(0);

  const pc = useRef<any>(null);
  const localRef = useRef<any>(null);
  const lastSignal = useRef(0);
  const pendingIce = useRef<any[]>([]);
  const outIce = useRef<any[]>([]);
  const remoteSet = useRef(false);
  const ice = useRef<any[] | null>(null);
  const closed = useRef(false);
  const polling = useRef(false);

  const statusRef = useRef<Call['status'] | null>(null);
  statusRef.current = call?.status ?? null;

  // ---------- teardown ----------
  const cleanup = useCallback(() => {
    if (closed.current) return;
    closed.current = true;
    Vibration.cancel();
    try {
      pc.current?.close();
    } catch {}
    localRef.current?.getTracks?.().forEach((t: any) => t.stop());
    pc.current = null;
  }, []);
  useEffect(() => cleanup, [cleanup]);

  const finishLocal = useCallback(
    (c: Call) => {
      setCall(c);
      cleanup();
      setTimeout(() => (router.canGoBack() ? router.back() : router.replace('/chat')), 1800);
    },
    [cleanup],
  );

  // ---------- signalling ----------
  const flushIce = useCallback(async () => {
    if (!outIce.current.length) return;
    const batch = outIce.current.splice(0, 50);
    try {
      await api('POST', `/calls/${callId}/signal`, { kind: 'ice', payload: batch });
    } catch {}
  }, [callId]);

  const iceServers = async () => {
    if (!ice.current) ice.current = (await api<{ ice_servers: any[] }>('GET', '/calls/turn')).ice_servers;
    return ice.current;
  };

  const makePeer = useCallback(async () => {
    if (pc.current) return pc.current;
    const p = createPeer({ iceServers: await iceServers() });
    pc.current = p;
    localRef.current?.getTracks().forEach((t: any) => p.addTrack(t, localRef.current));
    p.onicecandidate = (e: any) => {
      if (e.candidate) {
        outIce.current.push(e.candidate.toJSON ? e.candidate.toJSON() : e.candidate);
        setTimeout(flushIce, 200);
      }
    };
    p.ontrack = (e: any) => {
      if (e.streams?.[0]) setRemote(e.streams[0]);
    };
    p.onconnectionstatechange = () => {
      const st = p.connectionState;
      if (st === 'connected') {
        setConnected(true);
        setNote(null);
      } else if (st === 'disconnected') setNote('Reconnecting…');
      else if (st === 'failed') setNote('Connection failed. Check your internet.');
    };
    return p;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [flushIce]);

  const handleSignals = useCallback(
    async (list: Signal[]) => {
      for (const s of list) {
        lastSignal.current = Math.max(lastSignal.current, s.id);
        const p = await makePeer();
        if (s.kind === 'offer') {
          await p.setRemoteDescription(sessionDescription(s.payload));
          remoteSet.current = true;
          const answer = await p.createAnswer();
          await p.setLocalDescription(answer);
          await api('POST', `/calls/${callId}/signal`, { kind: 'answer', payload: { type: answer.type, sdp: answer.sdp } });
        } else if (s.kind === 'answer') {
          if (!remoteSet.current) {
            await p.setRemoteDescription(sessionDescription(s.payload));
            remoteSet.current = true;
          }
        } else if (s.kind === 'ice') {
          if (remoteSet.current) await p.addIceCandidate(iceCandidate(s.payload)).catch(() => {});
          else pendingIce.current.push(s.payload);
        }
        if (remoteSet.current && pendingIce.current.length) {
          for (const c of pendingIce.current.splice(0)) await p.addIceCandidate(iceCandidate(c)).catch(() => {});
        }
      }
    },
    [callId, makePeer],
  );

  const poll = useCallback(async () => {
    if (polling.current || closed.current) return;
    polling.current = true;
    try {
      const r = await api<{ status: Call['status']; signals: Signal[] }>('GET', `/calls/${callId}/signals?after=${lastSignal.current}`);
      if (ENDED.includes(r.status)) {
        finishLocal(await api<Call>('GET', `/calls/${callId}`));
        return;
      }
      if (r.status !== statusRef.current) setCall(await api<Call>('GET', `/calls/${callId}`));
      // The callee only answers after tapping Accept
      if (r.signals.length && (r.status === 'active' || statusRef.current === 'active' || call?.outgoing)) await handleSignals(r.signals);
    } catch {
    } finally {
      polling.current = false;
    }
  }, [callId, finishLocal, handleSignals, call?.outgoing]);

  useSyncEvent('call.', (e) => {
    if (Number(e.payload?.call_id) === callId) poll();
  });

  useEffect(() => {
    const t = setInterval(poll, connected ? 3000 : 1000);
    return () => clearInterval(t);
  }, [poll, connected]);

  // ---------- start ----------
  useEffect(() => {
    (async () => {
      try {
        const c = await api<Call>('GET', `/calls/${callId}`);
        setCall(c);
        if (ENDED.includes(c.status)) return;
        if (!webrtcAvailable) return setNote('Calls are not supported in this browser.');
        if (c.outgoing) {
          const stream = await getMedia(c.kind === 'video');
          localRef.current = stream;
          setLocal(stream);
          const p = await makePeer();
          const offer = await p.createOffer({});
          await p.setLocalDescription(offer);
          await api('POST', `/calls/${callId}/signal`, { kind: 'offer', payload: { type: offer.type, sdp: offer.sdp } });
        } else if (c.status === 'ringing') {
          Vibration.vibrate([0, 800, 1200], true);
        }
      } catch (e: any) {
        setNote(String(e?.message ?? '').toLowerCase().includes('permission') ? 'Microphone or camera access was denied.' : 'Could not start the call.');
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [callId]);

  // Call timer
  useEffect(() => {
    if (!connected) return;
    const t = setInterval(() => setSecs((s) => s + 1), 1000);
    return () => clearInterval(t);
  }, [connected]);

  // ---------- actions ----------
  const accept = async () => {
    Vibration.cancel();
    try {
      const stream = await getMedia(call?.kind === 'video');
      localRef.current = stream;
      setLocal(stream);
      const c = await api<Call & { ice_servers: any[] }>('POST', `/calls/${callId}/accept`);
      ice.current = c.ice_servers;
      setCall(c);
      await makePeer();
      poll();
    } catch (e: any) {
      setNote(e?.message ?? 'Could not answer.');
    }
  };
  const decline = async () => {
    Vibration.cancel();
    finishLocal(await api<Call>('POST', `/calls/${callId}/decline`).catch(() => ({ ...(call as Call), status: 'declined' as const })));
  };
  const hangup = async () => {
    finishLocal(await api<Call>('POST', `/calls/${callId}/end`, { reason: 'hangup' }).catch(() => ({ ...(call as Call), status: 'ended' as const })));
  };
  const toggleMute = () => {
    localRef.current?.getAudioTracks().forEach((t: any) => (t.enabled = muted));
    setMuted(!muted);
  };
  const toggleCam = () => {
    localRef.current?.getVideoTracks().forEach((t: any) => (t.enabled = camOff));
    setCamOff(!camOff);
  };

  const p = couple.data?.partner;
  const ended = call && ENDED.includes(call.status);
  const video = call?.kind === 'video';
  const status = ended
    ? endLabel[call!.status]
    : call?.status === 'ringing'
      ? call.outgoing
        ? 'Ringing…'
        : `Incoming ${video ? 'video' : 'voice'} call`
      : connected
        ? `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`
        : 'Connecting…';

  return (
    <View style={{ flex: 1, backgroundColor: '#14101A' }}>
      {video && remote && !ended ? <Video stream={remote} style={StyleSheet.absoluteFill} /> : null}
      {!video && remote && !ended ? <Video stream={remote} style={{ width: 1, height: 1, opacity: 0 }} /> : null}
      <SafeAreaView style={{ flex: 1, justifyContent: 'space-between', padding: 24 }}>
        <View style={{ alignItems: 'center', gap: 10, marginTop: 30 }}>
          {!(video && remote) || ended ? <Avatar url={p?.avatar_url} name={p?.display_name} size={110} /> : null}
          <T size={26} weight="700" color="#fff">{p?.display_name ?? ''}</T>
          <T color="#ffffffCC">{status}</T>
          {note ? <T color="#FFB4B4" center>{note}</T> : null}
        </View>

        {video && local && !ended ? (
          <View style={{ position: 'absolute', right: 20, top: 80, width: 110, height: 160, borderRadius: 16, overflow: 'hidden', backgroundColor: '#000' }}>
            {!camOff ? <Video stream={local} mirror style={{ width: '100%', height: '100%' }} /> : null}
          </View>
        ) : null}

        {!ended ? (
          call?.status === 'ringing' && !call.outgoing ? (
            <View style={{ flexDirection: 'row', justifyContent: 'space-around' }}>
              <RoundBtn icon="close" color="#E5484D" label="Decline" onPress={decline} />
              <RoundBtn icon={video ? 'videocam' : 'call'} color="#30A46C" label="Accept" onPress={accept} />
            </View>
          ) : (
            <View style={{ flexDirection: 'row', justifyContent: 'space-around', alignItems: 'flex-end' }}>
              <RoundBtn icon={muted ? 'mic-off' : 'mic'} color={muted ? '#ffffff' : '#ffffff33'} iconColor={muted ? '#14101A' : '#fff'} label={muted ? 'Unmute' : 'Mute'} onPress={toggleMute} />
              {video ? <RoundBtn icon={camOff ? 'videocam-off' : 'videocam'} color={camOff ? '#ffffff' : '#ffffff33'} iconColor={camOff ? '#14101A' : '#fff'} label="Camera" onPress={toggleCam} /> : null}
              {video ? <RoundBtn icon="camera-reverse" color="#ffffff33" label="Flip" onPress={() => switchCamera(localRef.current)} /> : null}
              <RoundBtn icon="call" color="#E5484D" label="End" onPress={hangup} rotate />
            </View>
          )
        ) : (
          <View />
        )}
      </SafeAreaView>
    </View>
  );
}

function RoundBtn({ icon, color, iconColor = '#fff', label, onPress, rotate }: { icon: React.ComponentProps<typeof Ionicons>['name']; color: string; iconColor?: string; label: string; onPress: () => void; rotate?: boolean }) {
  return (
    <View style={{ alignItems: 'center', gap: 6 }}>
      <Pressable onPress={onPress} accessibilityLabel={label} style={({ pressed }) => ({ width: 68, height: 68, borderRadius: 34, backgroundColor: color, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.8 : 1 })}>
        <Ionicons name={icon} size={30} color={iconColor} style={rotate ? { transform: [{ rotate: '135deg' }] } : undefined} />
      </Pressable>
      <T size={12} color="#ffffffCC">{label}</T>
    </View>
  );
}
