import { CameraView, useCameraPermissions } from 'expo-camera';
import { router } from 'expo-router';
import React, { useRef, useState } from 'react';
import { View } from 'react-native';
import { Button, Card, ErrorBox, Header, Muted, Screen, T } from '../components/ui';
import { api } from '../lib/api';

export default function Scan() {
  const [perm, requestPerm] = useCameraPermissions();
  const [err, setErr] = useState<unknown>(null);
  const [done, setDone] = useState(false);
  const handled = useRef(false);

  const onScan = async ({ data }: { data: string }) => {
    if (handled.current || !data.startsWith('coupleconnect://pair')) return;
    handled.current = true;
    try {
      await api('POST', '/couple/join', { qr_token: data });
      setDone(true);
    } catch (e) {
      setErr(e);
      handled.current = false;
    }
  };

  if (done) {
    return (
      <Screen>
        <Header title="Request sent" />
        <Card>
          <T>Your partner now needs to approve on their phone.</T>
        </Card>
        <Button title="Back" onPress={() => router.back()} />
      </Screen>
    );
  }

  if (!perm?.granted) {
    return (
      <Screen>
        <Header title="Scan code" onBack={() => router.back()} />
        <Muted>Camera access is only used to read your partner's QR code. Nothing is recorded.</Muted>
        {perm && !perm.canAskAgain ? <Muted>Camera access is off. Turn it on in Android settings, or type the 6-digit code instead.</Muted> : null}
        <Button title="Allow camera" onPress={requestPerm} disabled={perm ? !perm.canAskAgain : false} />
      </Screen>
    );
  }

  return (
    <Screen scroll={false}>
      <Header title="Scan code" onBack={() => router.back()} />
      <View style={{ flex: 1, borderRadius: 24, overflow: 'hidden' }}>
        <CameraView style={{ flex: 1 }} facing="back" barcodeScannerSettings={{ barcodeTypes: ['qr'] }} onBarcodeScanned={onScan} />
      </View>
      {err ? <ErrorBox error={err} /> : <Muted center>Point the camera at the QR code on your partner's screen.</Muted>}
    </Screen>
  );
}
