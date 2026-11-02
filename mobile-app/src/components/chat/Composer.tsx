import { Ionicons } from '@expo/vector-icons';
import { RecordingPresets, requestRecordingPermissionsAsync, setAudioModeAsync, useAudioRecorder, useAudioRecorderState } from 'expo-audio';
import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import React, { useState } from 'react';
import { ActivityIndicator, Alert, Platform, Pressable, TextInput, View } from 'react-native';
import { Message, uploadMedia } from '../../lib/api';
import { shrinkPhoto } from '../../lib/compress';
import { useSync } from '../../state/SyncProvider';
import { FONT, useTheme } from '../../state/ThemeProvider';
import { cardStyle, errorText, IconBubble, T } from '../ui';
import { preview } from './MessageBubble';
import { t } from '../../lib/i18n';

type Props = {
  replyTo: Message | null;
  onCancelReply: () => void;
  editing: Message | null;
  onCancelEdit: () => void;
  onSaveEdit: (m: Message, body: string) => Promise<void>;
};

export function Composer({ replyTo, onCancelReply, editing, onCancelEdit, onSaveEdit }: Props) {
  const { c } = useTheme();
  const { send, typing } = useSync();
  const [text, setText] = useState('');
  const [menu, setMenu] = useState(false);
  const [uploading, setUploading] = useState<string | null>(null);
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const rec = useAudioRecorderState(recorder, 250);

  React.useEffect(() => {
    if (editing) setText(editing.body ?? '');
  }, [editing]);

  const submit = async () => {
    const body = text.trim();
    if (!body) return;
    if (editing) {
      try {
        await onSaveEdit(editing, body);
        setText('');
      } catch (e) {
        Alert.alert(t('Could not edit'), errorText(e));
      }
      return;
    }
    send({ type: 'text', body, reply_to_id: replyTo?.id ?? null });
    setText('');
    onCancelReply();
  };

  const sendFile = async (file: { uri: string; name: string; type: string }, kind: 'image' | 'video' | 'voice' | 'file', durationMs?: number) => {
    setUploading(kind === 'voice' ? 'Sending voice note…' : 'Uploading…');
    try {
      const media = await uploadMedia(file, kind, durationMs);
      send({ type: kind, media_id: media.id, reply_to_id: replyTo?.id ?? null, local_uri: kind === 'image' ? file.uri : undefined });
      onCancelReply();
    } catch (e) {
      Alert.alert(t('Upload failed'), errorText(e));
    } finally {
      setUploading(null);
    }
  };

  const pickMedia = async (camera: boolean) => {
    setMenu(false);
    if (camera) {
      const p = await ImagePicker.requestCameraPermissionsAsync();
      if (!p.granted) return Alert.alert(t('Camera access needed'), t('Allow camera access in settings to take photos.'));
    }
    const opts: ImagePicker.ImagePickerOptions = { mediaTypes: ['images', 'videos'], quality: 0.85, videoMaxDuration: 300 };
    const r = camera ? await ImagePicker.launchCameraAsync(opts) : await ImagePicker.launchImageLibraryAsync(opts);
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    const isVideo = a.type === 'video';
    const picked = { uri: a.uri, name: a.fileName ?? (isVideo ? 'video.mp4' : 'photo.jpg'), type: a.mimeType ?? (isVideo ? 'video/mp4' : 'image/jpeg') };
    const file = isVideo ? picked : await shrinkPhoto({ ...picked, width: a.width, height: a.height });
    await sendFile(file, isVideo ? 'video' : 'image', isVideo && a.duration ? a.duration : undefined);
  };

  const pickDoc = async () => {
    setMenu(false);
    const r = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true, multiple: false });
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    await sendFile({ uri: a.uri, name: a.name, type: a.mimeType ?? 'application/octet-stream' }, 'file');
  };

  const startRec = async () => {
    const p = await requestRecordingPermissionsAsync();
    if (!p.granted) return Alert.alert(t('Microphone access needed'), t('Allow microphone access in settings to send voice notes.'));
    await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
    await recorder.prepareToRecordAsync();
    recorder.record();
  };

  const stopRec = async (keep: boolean) => {
    const duration = rec.durationMillis;
    await recorder.stop();
    await setAudioModeAsync({ allowsRecording: false });
    const uri = recorder.uri;
    if (!keep || !uri || duration < 700) return;
    const ext = Platform.OS === 'web' ? 'webm' : 'm4a';
    await sendFile({ uri, name: `voice.${ext}`, type: Platform.OS === 'web' ? 'audio/webm' : 'audio/mp4' }, 'voice', duration);
  };

  const banner = editing ? { title: 'Editing message', body: editing.body ?? '', cancel: () => { onCancelEdit(); setText(''); } } : replyTo ? { title: `Replying to ${replyTo.mine ? 'yourself' : 'partner'}`, body: preview(replyTo), cancel: onCancelReply } : null;

  return (
    <View style={[cardStyle(c), { marginHorizontal: 10, marginBottom: 10, marginTop: 4, borderRadius: 28 }]}>
      {banner ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', padding: 10, paddingBottom: 0, gap: 8 }}>
          <View style={{ flex: 1, borderLeftWidth: 3, borderLeftColor: c.primary, paddingLeft: 8 }}>
            <T size={12} weight="700" color={c.primary}>{banner.title}</T>
            <T size={12} numberOfLines={1} color={c.muted}>{banner.body}</T>
          </View>
          <Pressable onPress={banner.cancel} hitSlop={10} accessibilityLabel="Cancel"><Ionicons name="close" size={20} color={c.muted} /></Pressable>
        </View>
      ) : null}
      {menu ? (
        <View style={{ flexDirection: 'row', justifyContent: 'space-around', paddingTop: 12 }}>
          {[
            ['images-outline', 'Gallery', () => pickMedia(false)],
            ['camera-outline', 'Camera', () => pickMedia(true)],
            ['document-outline', 'File', pickDoc],
          ].map(([icon, label, fn]) => (
            <Pressable key={label as string} onPress={fn as () => void} style={{ alignItems: 'center', gap: 4 }}>
              <IconBubble name={icon as any} size={52} filled />
              <T size={12}>{label as string}</T>
            </Pressable>
          ))}
        </View>
      ) : null}
      {uploading ? (
        <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center', paddingHorizontal: 14, paddingTop: 10 }}>
          <ActivityIndicator color={c.primary} size="small" />
          <T size={13} color={c.muted}>{uploading}</T>
        </View>
      ) : null}
      {rec.isRecording ? (
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10 }}>
          <Pressable onPress={() => stopRec(false)} hitSlop={8} accessibilityLabel="Cancel recording"><Ionicons name="trash-outline" size={26} color={c.danger} /></Pressable>
          <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: c.danger }} />
            <T>Recording {Math.floor(rec.durationMillis / 60000)}:{String(Math.floor((rec.durationMillis / 1000) % 60)).padStart(2, '0')}</T>
          </View>
          <RoundAction onPress={() => stopRec(true)} label="Send voice note" icon="send" />
        </View>
      ) : (
        <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 4, padding: 6 }}>
          {!editing ? (
            <Pressable onPress={() => setMenu((v) => !v)} style={{ padding: 8 }} accessibilityLabel="Attach">
              <Ionicons name={menu ? 'close-circle' : 'add-circle'} size={30} color={c.primary} />
            </Pressable>
          ) : null}
          <TextInput
            value={text}
            onChangeText={(t) => {
              setText(t);
              if (t && !editing) typing();
            }}
            placeholder="Message"
            placeholderTextColor={c.muted}
            multiline
            maxLength={4000}
            style={{ flex: 1, maxHeight: 120, minHeight: 44, backgroundColor: 'transparent', color: c.text, borderRadius: 22, paddingHorizontal: 6, paddingTop: 11, paddingBottom: 11, fontSize: 16 * c.fontScale, fontFamily: FONT.medium }}
          />
          {text.trim() || editing ? (
            <RoundAction onPress={submit} label={editing ? 'Save edit' : 'Send'} icon={editing ? 'checkmark' : 'send'} />
          ) : (
            <RoundAction onPress={startRec} disabled={!!uploading} label="Record voice note" icon="mic" />
          )}
        </View>
      )}
    </View>
  );
}

function RoundAction({ onPress, label, icon, disabled }: { onPress: () => void; label: string; icon: React.ComponentProps<typeof Ionicons>['name']; disabled?: boolean }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={onPress} disabled={disabled} accessibilityLabel={label} style={({ pressed }) => ({ transform: [{ scale: pressed ? 0.9 : 1 }], opacity: disabled ? 0.5 : 1 })}>
      <LinearGradient colors={c.grad} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' }}>
        <Ionicons name={icon} size={20} color="#fff" />
      </LinearGradient>
    </Pressable>
  );
}
