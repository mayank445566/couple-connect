import { Stack } from 'expo-router';
import React from 'react';
import { useTheme } from '../../state/ThemeProvider';

export default function AuthLayout() {
  const { c } = useTheme();
  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: c.background } }} />;
}
