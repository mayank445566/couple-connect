import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as Linking from 'expo-linking';
import { router, useLocalSearchParams } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Button, ErrorBox, Muted, T } from '../components/ui';
import { API_URL, authedSource, signedMediaUrl } from '../lib/api';

/** Full-screen viewer for private media. Video and files use a 15-minute signed link, never a permanent URL. */
export default function MediaViewer() {
  const { id, type, name } = useLocalSearchParams<{ id: string; type: string; mime?: string; name?: string }>();
  const [signed, setSigned] = useState<string | null>(null);
  const [err, setErr] = useState<unknown>(null);

  useEffect(() => {
    if (type === 'image') return;
    signedMediaUrl(Number(id)).then(setSigned).catch(setErr);
  }, [id, type]);

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: '#000' }}>
      <View style={{ flexDirection: 'row', padding: 12, alignItems: 'center', gap: 12 }}>
        <Pressable onPress={() => router.back()} hitSlop={12} accessibilityLabel="Close">
          <Ionicons name="close" size={28} color="#fff" />
        </Pressable>
        <T color="#fff" numberOfLines={1} style={{ flex: 1 }}>{name || ''}</T>
      </View>
      <View style={{ flex: 1, justifyContent: 'center', padding: type === 'file' || type === 'audio' ? 24 : 0 }}>
        {err ? <ErrorBox error={err} /> : null}
        {type === 'image' ? <Image source={authedSource(`${API_URL}/media/${id}`)} style={{ flex: 1 }} contentFit="contain" /> : null}
        {type === 'video' && signed ? <Video url={signed} /> : null}
        {(type === 'file' || type === 'audio') && signed ? (
          <View style={{ gap: 12, alignItems: 'center' }}>
            <Ionicons name="document-outline" size={64} color="#fff" />
            <Muted center>Opens in your browser or another app. The link stops working after 15 minutes.</Muted>
            <Button title="Open file" icon="open-outline" onPress={() => Linking.openURL(signed)} />
          </View>
        ) : null}
        {type !== 'image' && !signed && !err ? <ActivityIndicator color="#fff" /> : null}
      </View>
    </SafeAreaView>
  );
}

function Video({ url }: { url: string }) {
  const player = useVideoPlayer(url, (p) => p.play());
  return <VideoView player={player} style={{ flex: 1 }} nativeControls contentFit="contain" />;
}
