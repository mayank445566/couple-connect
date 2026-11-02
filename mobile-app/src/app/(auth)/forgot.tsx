import { router } from 'expo-router';
import React, { useState } from 'react';
import { Button, Card, ErrorBox, Field, Header, Muted, Screen, T, fieldErrors } from '../../components/ui';
import { api } from '../../lib/api';

export default function Forgot() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [done, setDone] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);

  const run = async (fn: () => Promise<void>) => {
    setBusy(true);
    setErr(null);
    try {
      await fn();
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  if (done) {
    return (
      <Screen>
        <Header title="Password changed" onBack={() => router.back()} />
        <Card>
          <T>Your password was updated and every device was signed out. Sign in with your new password.</T>
        </Card>
        <Button title="Sign in" onPress={() => router.replace('/login')} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Header title="Reset password" onBack={() => router.back()} />
      <Field label="Email" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" error={f.email} editable={!sent} />
      {!sent ? (
        <Button title="Email me a code" loading={busy} disabled={!email} onPress={() => run(async () => { await api('POST', '/auth/forgot-password', { email: email.trim() }); setSent(true); })} />
      ) : (
        <>
          <Muted>If an account exists for this email, a 6-digit code is on its way.</Muted>
          <Field label="Code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} error={f.code} />
          <Field label="New password" value={password} onChangeText={setPassword} secureTextEntry error={f.password} />
          <Button title="Set new password" loading={busy} disabled={code.length !== 6 || !password} onPress={() => run(async () => { await api('POST', '/auth/reset-password', { email: email.trim(), code, password }); setDone(true); })} />
        </>
      )}
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
    </Screen>
  );
}
