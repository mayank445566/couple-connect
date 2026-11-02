// Native WebRTC (Android/iOS) through react-native-webrtc. The browser build uses webrtc.web.ts.
import React from 'react';
import { StyleProp, ViewStyle } from 'react-native';
import { mediaDevices, RTCIceCandidate, RTCPeerConnection, RTCSessionDescription, RTCView } from 'react-native-webrtc';

export const webrtcAvailable = true;

export function createPeer(config: { iceServers: any[] }): any {
  return new RTCPeerConnection(config as any);
}

export async function getMedia(video: boolean): Promise<any> {
  return mediaDevices.getUserMedia({ audio: true, video: video ? { facingMode: 'user', width: 640, height: 480, frameRate: 24 } : false } as any);
}

export const iceCandidate = (c: any) => new RTCIceCandidate(c);
export const sessionDescription = (d: any) => new RTCSessionDescription(d);

export function Video({ stream, mirror, style }: { stream: any; mirror?: boolean; style?: StyleProp<ViewStyle> }) {
  if (!stream) return null;
  return React.createElement(RTCView as any, { streamURL: stream.toURL(), mirror, objectFit: 'cover', style, zOrder: mirror ? 1 : 0 });
}

/** Switch between front and back camera on a local video track. */
export function switchCamera(stream: any) {
  stream?.getVideoTracks?.().forEach((t: any) => t._switchCamera?.());
}
