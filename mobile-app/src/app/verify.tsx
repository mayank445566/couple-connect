import React, { useState } from 'react';
import { Button, Card, ErrorBox, Field, H1, Muted, Screen, T, fieldErrors } from '../components/ui';
import { api } from '../lib/api';
import { useAuth } from '../state/AuthProvider';

export default function Verify() {
  const { me, refresh, signOut } = useAuth();
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);

  const submit = async () => {
    setBusy(true);
    setErr(null);
    try {
      await api('POST', '/auth/verify-email', { code });
      await refresh();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  const resend = async () => {
    setErr(null);
    try {
      await api('POST', '/auth/resend-verification');
      setNote('A new code is on its way.');
    } catch (e) {
      setErr(e);
    }
  };

  return (
    <Screen>
      <H1>Check your email</H1>
      <Card>
        <T>We sent a 6-digit code to</T>
        <T weight="700">{me?.email ?? 'your email'}</T>
        <Muted>It expires in 15 minutes. Check spam if you don't see it.</Muted>
      </Card>
      <Field label="Code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} error={f.code} autoComplete="one-time-code" onSubmitEditing={submit} />
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Button title="Verify" onPress={submit} loading={busy} disabled={code.length !== 6} />
      <Button title="Resend code" variant="ghost" onPress={resend} />
      {note ? <Muted center>{note}</Muted> : null}
      <Button title="Sign out" variant="ghost" onPress={() => signOut()} />
    </Screen>
  );
}
