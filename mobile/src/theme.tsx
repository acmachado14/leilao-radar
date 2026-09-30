import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';
import * as SystemUI from 'expo-system-ui';
import { StatusBar } from 'expo-status-bar';

export type ThemeScheme = 'light' | 'dark';

export type ThemeColors = {
  bg: string;
  card: string;
  border: string;
  text: string;
  muted: string;
  dim: string;
  accent: string;
  accentDark: string;
  accentSoft: string;
  onAccent: string;
  danger: string;
  amber: string;
  info: string;
  photoBg: string;
  overlay: string;
  onOverlay: string;
};

export const palettes: Record<ThemeScheme, ThemeColors> = {
  dark: {
    bg: '#020617',
    card: '#0f172a',
    border: '#1e293b',
    text: '#f8fafc',
    muted: '#94a3b8',
    dim: '#64748b',
    accent: '#34d399',
    accentDark: '#10b981',
    accentSoft: 'rgba(52, 211, 153, 0.12)',
    onAccent: '#020617',
    danger: '#f87171',
    amber: '#fcd34d',
    info: '#38bdf8',
    photoBg: '#0a1016',
    overlay: 'rgba(8, 14, 20, 0.78)',
    onOverlay: '#f8fafc',
  },
  light: {
    bg: '#f2f2f7',
    card: '#ffffff',
    border: '#e5e7eb',
    text: '#0f172a',
    muted: '#64748b',
    dim: '#94a3b8',
    accent: '#059669',
    accentDark: '#047857',
    accentSoft: 'rgba(5, 150, 105, 0.12)',
    onAccent: '#ffffff',
    danger: '#dc2626',
    amber: '#d97706',
    info: '#0284c7',
    photoBg: '#e2e8f0',
    overlay: 'rgba(15, 23, 42, 0.75)',
    onOverlay: '#f8fafc',
  },
};

type ThemeValue = {
  scheme: ThemeScheme;
  colors: ThemeColors;
};

const ThemeContext = createContext<ThemeValue>({
  scheme: 'light',
  colors: palettes.light,
});

export function ThemeProvider({ children }: { children: ReactNode }) {
  const scheme: ThemeScheme = 'light';
  const colors = palettes.light;
  const value = useMemo(() => ({ scheme, colors }), [scheme, colors]);

  useEffect(() => {
    SystemUI.setBackgroundColorAsync(colors.bg).catch(() => undefined);
  }, [colors.bg]);

  return (
    <ThemeContext.Provider value={value}>
      <StatusBar style="dark" />
      {children}
    </ThemeContext.Provider>
  );
}

export function useAppTheme(): ThemeValue {
  return useContext(ThemeContext);
}
