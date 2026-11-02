import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { router } from 'expo-router';
import React, { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { Avatar, Button, Card, Field, Header, Muted, Row, Screen, T, errorText, IconBubble } from '../../components/ui';
import { api, Couple, Me, uploadMedia } from '../../lib/api';
import { shrinkPhoto } from '../../lib/compress';
import { useLoad } from '../../lib/useLoad';
import { useAuth } from '../../state/AuthProvider';
import { useTheme } from '../../state/ThemeProvider';
import { t } from '../../lib/i18n';

export default function Us() {
  const { c } = useTheme();
  const { me, setMe } = useAuth();
  const couple = useLoad(() => api<Couple>('GET', '/couple'));
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(me?.display_name ?? '');
  const [bio, setBio] = useState(me?.bio ?? '');
  const [busy, setBusy] = useState(false);

  const save = async (patch: Record<string, unknown>) => {
    setBusy(true);
    try {
      setMe(await api<Me>('PATCH', '/me', patch));
      setEditing(false);
    } catch (e) {
      Alert.alert(t('Could not save'), errorText(e));
    } finally {
      setBusy(false);
    }
  };

  const changeAvatar = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.85 });
    if (r.canceled || !r.assets[0]) return;
    try {
      const a0 = r.assets[0];
      const m = await uploadMedia(await shrinkPhoto({ uri: a0.uri, name: a0.fileName ?? 'avatar.jpg', type: a0.mimeType ?? 'image/jpeg', width: a0.width, height: a0.height }), 'avatar');
      await save({ avatar_media_id: m.id });
    } catch (e) {
      Alert.alert(t('Upload failed'), errorText(e));
    }
  };

  const p = couple.data?.partner;
  const links: [React.ComponentProps<typeof Ionicons>['name'], string, string][] = [
    ['calendar-outline', 'Our dates & disappearing messages', '/couple-dates'],
    ['color-palette-outline', 'Themes', '/themes'],
    ['diamond-outline', 'Premium', '/premium'],
    ['flame-outline', '18+ games', '/adult'],
    ['settings-outline', 'Settings & privacy', '/settings'],
  ];

  return (
    <Screen refreshing={couple.refreshing} onRefresh={couple.onRefresh}>
      <Header title="Us" />
      <Card>
        <Row>
          <Pressable onPress={changeAvatar} accessibilityLabel="Change photo">
            <Avatar url={me?.avatar_url} name={me?.display_name} size={64} />
            <View style={{ position: 'absolute', right: -2, bottom: -2, backgroundColor: c.primary, borderRadius: 12, padding: 4 }}>
              <Ionicons name="camera" size={12} color="#fff" />
            </View>
          </Pressable>
          <View style={{ flex: 1 }}>
            <T size={18} weight="700">{me?.display_name}</T>
            {me?.bio ? <Muted>{me.bio}</Muted> : <Muted>Add a short bio</Muted>}
          </View>
          {!editing ? <Button title="Edit" variant="ghost" onPress={() => { setName(me?.display_name ?? ''); setBio(me?.bio ?? ''); setEditing(true); }} /> : null}
        </Row>
        {editing ? (
          <>
            <Field label="Name" value={name} onChangeText={setName} maxLength={50} />
            <Field label="Bio" value={bio} onChangeText={setBio} maxLength={160} multiline />
            <Row>
              <Button title="Save" style={{ flex: 1 }} onPress={() => save({ display_name: name.trim(), bio: bio.trim() })} loading={busy} disabled={!name.trim()} />
              <Button title="Cancel" variant="ghost" onPress={() => setEditing(false)} />
            </Row>
          </>
        ) : null}
      </Card>

      {p ? (
        <Card>
          <Muted>Your partner</Muted>
          <Row>
            <Avatar url={p.avatar_url} name={p.display_name} size={52} />
            <View style={{ flex: 1 }}>
              <T weight="700">{p.display_name}</T>
              {p.bio ? <Muted>{p.bio}</Muted> : null}
            </View>
          </Row>
          <Muted>Connected {new Date(couple.data!.paired_at).toLocaleDateString()} · {couple.data!.days_together} days together</Muted>
        </Card>
      ) : null}

      {links.map(([icon, label, to]) => (
        <Card key={to} onPress={() => router.push(to as any)} style={{ paddingVertical: 14 }}>
          <Row>
            <IconBubble name={icon} size={38} />
            <T style={{ flex: 1 }}>{label}</T>
            <Ionicons name="chevron-forward" size={18} color={c.muted} />
          </Row>
        </Card>
      ))}
    </Screen>
  );
}
