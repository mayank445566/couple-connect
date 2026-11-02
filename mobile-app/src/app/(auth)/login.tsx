import { router } from 'expo-router';
import React, { useState } from 'react';
import { Button, ErrorBox, Field, Header, Screen, fieldErrors } from '../../components/ui';
import { api, Me } from '../../lib/api';
import { deviceInfo, useAuth } from '../../state/AuthProvider';

export default function Login() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);

  const submit = async () => {
    setBusy(true);
    setErr(null);
    try {
      const r = await api<{ token: string; user: Me }>('POST', '/auth/login', { email: email.trim(), password, ...deviceInfo() });
      await signIn(r.token, r.user);
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <Header title="Welcome back" onBack={() => router.back()} />
      <Field label="Email" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" error={f.email} />
      <Field label="Password" value={password} onChangeText={setPassword} secureTextEntry autoComplete="password" error={f.password} onSubmitEditing={submit} />
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Button title="Sign in" onPress={submit} loading={busy} disabled={!email || !password} />
      <Button title="Forgot password?" variant="ghost" onPress={() => router.push('/forgot')} />
    </Screen>
  );
}
