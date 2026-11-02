import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { Pressable, View } from 'react-native';
import { useTheme } from '../state/ThemeProvider';
import { Field, Muted, Pill, Row, T } from './ui';

export const STATUSES = [
  ['dating', 'Dating'],
  ['engaged', 'Engaged'],
  ['married', 'Married'],
  ['long_distance', 'Long distance'],
  ['its_complicated', "It's complicated"],
] as const;

/** Accepts DD-MM-YYYY or YYYY-MM-DD and returns YYYY-MM-DD for the API. */
export function normalizeDob(v: string): string {
  const s = v.trim();
  const m = s.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
  if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
  return s;
}

export function RegisterFields({ v, set, f }: { v: Record<string, string>; set: (k: string, val: string) => void; f: Record<string, string> }) {
  return (
    <>
      <Field label="Your name" value={v.display_name} onChangeText={(t) => set('display_name', t)} error={f.display_name} autoComplete="name" />
      <Field label="Date of birth (DD-MM-YYYY)" value={v.dob} onChangeText={(t) => set('dob', t)} error={f.dob} placeholder="21-04-1998" keyboardType="numbers-and-punctuation" />
      <View style={{ gap: 6 }}>
        <Muted>Relationship</Muted>
        <Row style={{ flexWrap: 'wrap' }} gap={8}>
          {STATUSES.map(([k, label]) => (
            <Pill key={k} label={label} active={v.relationship_status === k} onPress={() => set('relationship_status', k)} />
          ))}
        </Row>
      </View>
    </>
  );
}

export function TermsCheck({ value, onChange, error }: { value: boolean; onChange: (v: boolean) => void; error?: string }) {
  const { c } = useTheme();
  return (
    <Pressable onPress={() => onChange(!value)} style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }} accessibilityRole="checkbox" accessibilityState={{ checked: value }}>
      <Ionicons name={value ? 'checkbox' : 'square-outline'} size={24} color={error ? c.danger : c.primary} />
      <T style={{ flex: 1 }} size={13}>I am 18 or older and agree to the Terms of Service and Privacy Policy.</T>
    </Pressable>
  );
}

