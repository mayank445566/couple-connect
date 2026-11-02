import { router, useLocalSearchParams } from 'expo-router';
import React, { useState } from 'react';
import { Button, Card, ErrorBox, Field, Header, Muted, Pill, Row, Screen, T } from '../components/ui';
import { api } from '../lib/api';
import { useLoad } from '../lib/useLoad';

const REASONS = [
  ['harassment', 'Harassment'],
  ['abuse', 'Abuse or threats'],
  ['underage', 'Someone is under 18'],
  ['impersonation', 'Impersonation'],
  ['spam', 'Spam or scam'],
  ['other', 'Something else'],
] as const;

/** Sends a report to the moderators. Only the selected message is shared with them, never the whole chat. */
export default function Report() {
  const { message_id } = useLocalSearchParams<{ message_id?: string }>();
  const couple = useLoad(() => api<{ partner: { id: number } }>('GET', '/couple').catch(() => null));
  const [reason, setReason] = useState<string | null>(null);
  const [details, setDetails] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const [done, setDone] = useState(false);

  const submit = async () => {
    setBusy(true);
    setErr(null);
    try {
      await api('POST', '/reports', {
        reason,
        details: details.trim() || undefined,
        reported_user_id: couple.data?.partner.id,
        message_ids: message_id ? [Number(message_id)] : undefined,
      });
      setDone(true);
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  if (done) {
    return (
      <Screen>
        <Header title="Report sent" onBack={() => router.back()} />
        <Card>
          <T>Thank you. Our team will review it. If you feel unsafe, you can unpair and block from Settings at any time. In an emergency, call 112.</T>
        </Card>
        <Button title="Done" onPress={() => router.back()} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Header title="Report" onBack={() => router.back()} />
      <Muted>{message_id ? 'The selected message will be attached so moderators can see it. Nothing else from your chat is shared.' : 'Tell us what happened.'}</Muted>
      <Row style={{ flexWrap: 'wrap' }} gap={8}>
        {REASONS.map(([k, l]) => (
          <Pill key={k} label={l} active={reason === k} onPress={() => setReason(k)} />
        ))}
      </Row>
      <Field label="Details (optional)" value={details} onChangeText={setDetails} multiline maxLength={2000} style={{ minHeight: 100, textAlignVertical: 'top' }} />
      {err ? <ErrorBox error={err} /> : null}
      <Button title="Send report" variant="danger" onPress={submit} disabled={!reason} loading={busy} />
    </Screen>
  );
}
