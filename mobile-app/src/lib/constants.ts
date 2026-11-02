import type { IconName } from '../components/ui';

/** Seed icons are short generic names; map them to Ionicons. */
const GAME_ICONS: Record<string, IconName> = {
  brain: 'bulb-outline', calendar: 'calendar-outline', cards: 'albums-outline', chat: 'chatbubbles-outline', clock: 'time-outline',
  dice: 'dice-outline', edit: 'create-outline', flame: 'flame-outline', gift: 'gift-outline', globe: 'globe-outline',
  hand: 'hand-left-outline', heart: 'heart-outline', image: 'image-outline', key: 'key-outline', link: 'link-outline',
  lock: 'lock-closed-outline', mask: 'happy-outline', moon: 'moon-outline', scale: 'scale-outline', shuffle: 'shuffle-outline',
  smile: 'happy-outline', star: 'star-outline', sun: 'sunny-outline', target: 'locate-outline', theater: 'film-outline',
  trophy: 'trophy-outline', users: 'people-outline', wine: 'wine-outline',
};
export const gameIcon = (k: string | null | undefined): IconName => GAME_ICONS[k ?? ''] ?? 'game-controller-outline';

export const TAPS: { kind: string; label: string; emoji: string }[] = [
  { kind: 'hug', label: 'Hug', emoji: '🤗' },
  { kind: 'kiss', label: 'Kiss', emoji: '😘' },
  { kind: 'heartbeat', label: 'Heartbeat', emoji: '💓' },
  { kind: 'miss_you', label: 'Miss you', emoji: '🥺' },
  { kind: 'thinking_of_you', label: 'Thinking of you', emoji: '💭' },
  { kind: 'good_morning', label: 'Good morning', emoji: '☀️' },
  { kind: 'good_night', label: 'Good night', emoji: '🌙' },
];
export const tapLabel = (k: string) => TAPS.find((t) => t.kind === k) ?? { kind: k, label: k, emoji: '💗' };

export const MOODS: { mood: string; label: string; emoji: string }[] = [
  { mood: 'happy', label: 'Happy', emoji: '😊' },
  { mood: 'loved', label: 'Loved', emoji: '🥰' },
  { mood: 'excited', label: 'Excited', emoji: '🤩' },
  { mood: 'calm', label: 'Calm', emoji: '😌' },
  { mood: 'tired', label: 'Tired', emoji: '😴' },
  { mood: 'stressed', label: 'Stressed', emoji: '😣' },
  { mood: 'sad', label: 'Sad', emoji: '😢' },
  { mood: 'missing_you', label: 'Missing you', emoji: '🥺' },
  { mood: 'angry', label: 'Angry', emoji: '😠' },
];
export const moodOf = (m: string | null | undefined) => MOODS.find((x) => x.mood === m) ?? null;

export const REACTIONS = ['❤️', '😂', '😮', '😢', '🔥', '👍'];

export const HUB_LISTS = {
  countdowns: { title: 'Countdowns', icon: 'hourglass-outline' as IconName, empty: 'Count down to your next visit, a trip or a big day.' },
  bucket: { title: 'Bucket list', icon: 'checkbox-outline' as IconName, empty: 'Things you want to do together, one day.' },
  calendar: { title: 'Shared calendar', icon: 'calendar-outline' as IconName, empty: 'Dates, calls, trips and reminders for both of you.' },
  memories: { title: 'Memories', icon: 'images-outline' as IconName, empty: 'Your timeline of moments, with photos.' },
};
export type HubList = keyof typeof HUB_LISTS;

/** "2026-11-02" in the device's local time zone. */
export function localDate(d = new Date()): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function daysUntil(iso: string): number {
  return Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000);
}
