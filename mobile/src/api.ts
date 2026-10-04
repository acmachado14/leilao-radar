import { Platform } from 'react-native';
import { getToken, setToken } from './auth';
import type { ScreenDocument } from './sdui/types';

const BASE = (process.env.EXPO_PUBLIC_API_URL ?? 'https://radar.verifycar.com.br').replace(/\/$/, '');

async function headers(): Promise<Record<string, string>> {
  const token = await getToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-App-Version': '1.1.0',
    'X-App-Platform': Platform.OS === 'ios' ? 'ios' : 'android',
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

export async function fetchScreen(name: string, params: Record<string, string> = {}): Promise<ScreenDocument> {
  const query = new URLSearchParams(params).toString();
  const url = `${BASE}/bff/v1/screens/${encodeURIComponent(name)}${query ? `?${query}` : ''}`;
  const response = await fetch(url, { headers: await headers() });
  if (!response.ok) {
    throw new Error(`BFF screen ${name} failed (${response.status})`);
  }
  const document = (await response.json()) as ScreenDocument;
  if (document.token) {
    await setToken(document.token);
  }
  return document;
}

export async function postAction(name: string, payload: Record<string, unknown> = {}): Promise<ScreenDocument> {
  const response = await fetch(`${BASE}/bff/v1/actions/${encodeURIComponent(name)}`, {
    method: 'POST',
    headers: await headers(),
    body: JSON.stringify(payload),
  });
  if (!response.ok) {
    throw new Error(`BFF action ${name} failed (${response.status})`);
  }
  const document = (await response.json()) as ScreenDocument;
  if (document.token) {
    await setToken(document.token);
  }
  return document;
}

export async function fetchMe(): Promise<{ id: string } | null> {
  const response = await fetch(`${BASE}/api/v1/me`, { headers: await headers() });
  if (response.status === 401 || response.status === 403) {
    return null;
  }
  if (!response.ok) {
    throw new Error(`Perfil indisponível (${response.status})`);
  }
  const body = (await response.json()) as { user?: { id?: string } };
  return body.user?.id ? { id: String(body.user.id) } : null;
}

export async function resolveLaunchScreen(guestFallback = 'login'): Promise<string> {
  const token = await getToken();
  if (!token) {
    return guestFallback;
  }

  try {
    const me = await fetchMe();
    if (!me) {
      await setToken(null);
      return guestFallback;
    }
  } catch {
    // Keep the saved session when offline or the API is briefly unavailable.
    return 'catalog';
  }

  return 'catalog';
}
