import { router } from 'expo-router';
import React, { useState } from 'react';
import { Button, ErrorBox, Field, Header, Muted, Pill, Row, Screen, fieldErrors } from '../../components/ui';
import { api, Me } from '../../lib/api';
import { deviceInfo, useAuth } from '../../state/AuthProvider';
import { normalizeDob, RegisterFields, TermsCheck } from '../../components/forms';

/** Phone sign-in and sign-up with an SMS code. The server answers the same whether or not the number has an account. */
export default function Phone() {
  const { signIn } = useAuth();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [phone, setPhone] = useState('');
  const [sent, setSent] = useState(false);
  const [code, setCode] = useState('');
  const [v, setV] = useState<Record<string, string>>({ password: '', display_name: '', dob: '', relationship_status: 'dating' });
  const [terms, setTerms] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const f = fieldErrors(err);
  const set = (k: string, val: string) => setV((s) => ({ ...s, [k]: val }));

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

  const requestCode = () =>
    run(async () => {
      await api('POST', '/auth/phone/request-otp', { phone: phone.trim() });
      setSent(true);
    });

  const finish = () =>
    run(async () => {
      const r =
        mode === 'login'
          ? await api<{ token: string; user: Me }>('POST', '/auth/phone/login', { phone: phone.trim(), code, ...deviceInfo() })
          : await api<{ token: string; user: Me }>('POST', '/auth/phone/register', { phone: phone.trim(), code, ...v, dob: normalizeDob(v.dob), accept_terms: terms, ...deviceInfo() });
      await signIn(r.token, r.user);
    });

  return (
    <Screen>
      <Header title="Phone number" onBack={() => router.back()} />
      <Row gap={8}>
        <Pill label="I have an account" active={mode === 'login'} onPress={() => setMode('login')} />
        <Pill label="I'm new" active={mode === 'register'} onPress={() => setMode('register')} />
      </Row>
      <Field label="Mobile number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="+91 98765 43210" error={f.phone} editable={!sent} autoComplete="tel" />
      {!sent ? (
        <Button title="Send code" onPress={requestCode} loading={busy} disabled={phone.trim().length < 10} />
      ) : (
        <>
          <Field label="6-digit code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} error={f.code} autoComplete="sms-otp" textContentType="oneTimeCode" />
          {mode === 'register' ? (
            <>
              <Field label="Password" value={v.password} onChangeText={(t) => set('password', t)} secureTextEntry error={f.password} placeholder="8+ characters, a letter and a number" />
              <RegisterFields v={v} set={set} f={f} />
              <TermsCheck value={terms} onChange={setTerms} error={f.accept_terms} />
            </>
          ) : null}
          <Button title={mode === 'login' ? 'Sign in' : 'Create account'} onPress={finish} loading={busy} disabled={code.length !== 6 || (mode === 'register' && !terms)} />
          <Button title="Change number or resend" variant="ghost" onPress={() => setSent(false)} />
        </>
      )}
      {err && !Object.keys(f).length ? <ErrorBox error={err} /> : null}
      <Muted center>Standard SMS rates may apply.</Muted>
    </Screen>
  );
}
