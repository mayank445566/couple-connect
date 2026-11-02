import { router } from 'expo-router';
import React, { useState } from 'react';
import { normalizeDob, RegisterFields, TermsCheck } from '../../components/forms';
import { Button, ErrorBox, Field, Header, Muted, Screen, fieldErrors } from '../../components/ui';
import { api, Me } from '../../lib/api';
import { deviceInfo, useAuth } from '../../state/AuthProvider';

export default function Register() {
  const { signIn } = useAuth();
  const [v, setV] = useState<Record<string, string>>({ email: '', password: '', display_name: '', dob: '', relationship_status: 'dating' });
  const [terms, setTerms] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const set = (k: string, val: string) => setV((s) => ({ ...s, [k]: val }));

  const submit = async () => {
    setBusy(true);
    setErr(null);
    try {
      const r = await api<{ token: string; user: Me }>('POST', '/auth/register', {
        ...v,
        email: v.email.trim(),
        dob: normalizeDob(v.dob),
        accept_terms: terms,
        ...deviceInfo(),
      });
      await signIn(r.token, r.user);
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <Header title="Create your account" onBack={() => router.back()} />
      <Field label="Email" value={v.email} onChangeText={(t) => set('email', t)} autoCapitalize="none" keyboardType="email-address" autoComplete="email" error={f.email} />
      <Field label="Password" value={v.password} onChangeText={(t) => set('password', t)} secureTextEntry autoComplete="new-password" error={f.password} placeholder="8+ characters, a letter and a number" />
      <RegisterFields v={v} set={set} f={f} />
      <TermsCheck value={terms} onChange={setTerms} error={f.accept_terms} />
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Button title="Create account" onPress={submit} loading={busy} disabled={!terms || !v.email || !v.password || !v.display_name || !v.dob} />
      <Muted center>We'll email you a 6-digit code to confirm it's you.</Muted>
    </Screen>
  );
}
