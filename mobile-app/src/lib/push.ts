import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { api } from './api';

/**
 * Registers this device for push through Firebase Cloud Messaging.
 * Needs google-services.json in the build (see documentation/MOBILE_APP.md). Without it,
 * registration fails quietly and the app keeps working with in-app sync only.
 */
export async function registerForPush(): Promise<'registered' | 'denied' | 'unavailable'> {
  if (Platform.OS !== 'android') return 'unavailable';
  if (Constants.appOwnership === 'expo') return 'unavailable'; // Expo Go has no FCM for this app
  await Promise.all([
    Notifications.setNotificationChannelAsync('messages', { name: 'Messages', importance: Notifications.AndroidImportance.HIGH, lockscreenVisibility: Notifications.AndroidNotificationVisibility.PRIVATE }),
    Notifications.setNotificationChannelAsync('calls', { name: 'Calls', importance: Notifications.AndroidImportance.MAX, sound: 'default', vibrationPattern: [0, 800, 1200, 800], lockscreenVisibility: Notifications.AndroidNotificationVisibility.PUBLIC }),
    Notifications.setNotificationChannelAsync('games', { name: 'Game invites', importance: Notifications.AndroidImportance.DEFAULT }),
    Notifications.setNotificationChannelAsync('reminders', { name: 'Letters & reminders', importance: Notifications.AndroidImportance.DEFAULT }),
  ]);
  const perm = await Notifications.getPermissionsAsync();
  const granted = perm.granted || (await Notifications.requestPermissionsAsync()).granted;
  if (!granted) return 'denied';
  try {
    const t = await Notifications.getDevicePushTokenAsync();
    await api('POST', '/me/push-token', { token: String(t.data), platform: 'android' });
    return 'registered';
  } catch {
    return 'unavailable';
  }
}

export async function unregisterPush() {
  await api('DELETE', '/me/push-token').catch(() => {});
}
