// Browser WebRTC with the standard APIs. Same exports as webrtc.ts.
import React, { useEffect, useRef } from 'react';
import { StyleProp, ViewStyle } from 'react-native';

export const webrtcAvailable = typeof window !== 'undefined' && !!(window as any).RTCPeerConnection;

export function createPeer(config: { iceServers: any[] }): any {
  return new (window as any).RTCPeerConnection(config);
}

export async function getMedia(video: boolean): Promise<any> {
  return navigator.mediaDevices.getUserMedia({ audio: true, video: video ? { facingMode: 'user', width: 640, height: 480 } : false });
}

export const iceCandidate = (c: any) => new (window as any).RTCIceCandidate(c);
export const sessionDescription = (d: any) => new (window as any).RTCSessionDescription(d);

export function Video({ stream, mirror, style }: { stream: any; mirror?: boolean; style?: StyleProp<ViewStyle> }) {
  const ref = useRef<HTMLVideoElement | null>(null);
  useEffect(() => {
    if (ref.current) ref.current.srcObject = stream ?? null;
  }, [stream]);
  if (!stream) return null;
  const flat = Object.assign({}, ...(Array.isArray(style) ? style : [style]).filter(Boolean)) as any;
  return React.createElement('video', {
    ref,
    autoPlay: true,
    playsInline: true,
    muted: !!mirror, // local preview must not echo
    style: { objectFit: 'cover', transform: mirror ? 'scaleX(-1)' : undefined, ...flat },
  });
}

export function switchCamera(_stream: any) {
  // Browsers pick the camera through the permission prompt; nothing to switch here.
}
