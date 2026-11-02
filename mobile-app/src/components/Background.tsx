import * as Notifications from 'expo-notifications';
import { router, usePathname } from 'expo-router';
import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import { registerForPush } from '../lib/push';
import { useAuth } from '../state/AuthProvider';
import { useSyncEvent } from '../state/SyncProvider';

if (Platform.OS !== 'web') {
  // While the app is open, sync already shows new things (and calls open the call screen), so no banners or sounds
  Notifications.setNotificationHandler({
    handleNotification: async (n) => {
      const isCall = n.request.content.data?.type === 'call.ringing';
      return { shouldShowBanner: false, shouldShowList: !isCall, shouldPlaySound: false, shouldSetBadge: false };
    },
  });
}

/** App-wide listeners: incoming calls, push registration and notification taps. */
export function Background() {
  const { me } = useAuth();
  const path = usePathname();
  const pathRef = useRef(path);
  pathRef.current = path;

  // Incoming call while the app is open
  useSyncEvent('call.ringing', (e) => {
    if (e.actor_id === me?.id) return;
    const id = e.payload?.call_id;
    // Only ring for fresh events, not ones replayed from an old cursor
    if (id && Date.now() - new Date(e.at).getTime() < 45000 && !pathRef.current.startsWith('/call/')) router.push(`/call/${id}`);
  });

  useEffect(() => {
    if (!me?.paired || Platform.OS === 'web') return;
    registerForPush().catch(() => {});
    const open = (data: any) => {
      const url = typeof data?.url === 'string' && data.url.startsWith('/') ? data.url : null;
      if (url) router.push(url as any);
    };
    Notifications.getLastNotificationResponseAsync().then((r) => r && open(r.notification.request.content.data));
    const sub = Notifications.addNotificationResponseReceivedListener((r) => open(r.notification.request.content.data));
    return () => sub.remove();
  }, [me?.paired]);

  return null;
}
