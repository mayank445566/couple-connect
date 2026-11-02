import AsyncStorage from '@react-native-async-storage/async-storage';
import { AudioPlayer, createAudioPlayer, setAudioModeAsync } from 'expo-audio';

/** Short, soft sound effects. Off with one switch in Settings; they never play over silent mode on iOS. */
const FILES = {
  send: require('../../assets/sounds/send.wav'),
  receive: require('../../assets/sounds/receive.wav'),
  heart: require('../../assets/sounds/heart.wav'),
  flip: require('../../assets/sounds/flip.wav'),
  match: require('../../assets/sounds/match.wav'),
  sparkle: require('../../assets/sounds/sparkle.wav'),
  invite: require('../../assets/sounds/invite.wav'),
};
export type Sound = keyof typeof FILES;

const KEY = 'cc_sounds';
let enabled = true;
let ready = false;
const players: Partial<Record<Sound, AudioPlayer>> = {};

AsyncStorage.getItem(KEY)
  .then((v) => {
    enabled = v !== 'off';
  })
  .catch(() => {});

export function soundsEnabled() {
  return enabled;
}

export function setSoundsEnabled(on: boolean) {
  enabled = on;
  AsyncStorage.setItem(KEY, on ? 'on' : 'off').catch(() => {});
}

export function play(name: Sound) {
  if (!enabled) return;
  try {
    if (!ready) {
      ready = true;
      // Mix with music from other apps instead of stopping it, and respect the silent switch
      setAudioModeAsync({ playsInSilentMode: false, interruptionMode: 'mixWithOthers' } as any).catch(() => {});
    }
    let p = players[name];
    if (!p) {
      p = createAudioPlayer(FILES[name]);
      p.volume = 0.6;
      players[name] = p;
    }
    p.seekTo(0).catch(() => {});
    p.play();
  } catch {
    // A sound effect must never break the app
  }
}
