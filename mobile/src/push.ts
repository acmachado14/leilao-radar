import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { postAction } from './api';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

export type PushNavigation = {
  screen: string;
  params?: Record<string, string>;
};

export function parsePushNavigation(data: unknown): PushNavigation | null {
  if (!data || typeof data !== 'object') {
    return null;
  }
  const record = data as Record<string, unknown>;
  const screen = typeof record.screen === 'string' ? record.screen : '';
  if (!screen) {
    return null;
  }
  const params: Record<string, string> = {};
  if (typeof record.id === 'string') {
    params.id = record.id;
  }

  return { screen, params: Object.keys(params).length ? params : undefined };
}

export async function registerIosPushTokenIfPossible(): Promise<void> {
  if (Platform.OS !== 'ios' || !Device.isDevice) {
    return;
  }

  const { status: existing } = await Notifications.getPermissionsAsync();
  let finalStatus = existing;
  if (existing !== 'granted') {
    const requested = await Notifications.requestPermissionsAsync();
    finalStatus = requested.status;
  }
  if (finalStatus !== 'granted') {
    return;
  }

  const projectId =
    Constants.expoConfig?.extra?.eas?.projectId ??
    Constants.easConfig?.projectId ??
    Constants.expoConfig?.extra?.easProjectId;

  const token = await Notifications.getExpoPushTokenAsync(
    projectId ? { projectId: String(projectId) } : undefined,
  );

  await postAction('register_push', {
    expo_push_token: token.data,
    platform: 'ios',
  });
}

export async function unregisterIosPushToken(): Promise<void> {
  if (Platform.OS !== 'ios' || !Device.isDevice) {
    return;
  }

  try {
    const projectId =
      Constants.expoConfig?.extra?.eas?.projectId ??
      Constants.easConfig?.projectId ??
      Constants.expoConfig?.extra?.easProjectId;
    const token = await Notifications.getExpoPushTokenAsync(
      projectId ? { projectId: String(projectId) } : undefined,
    );
    await postAction('unregister_push', {
      expo_push_token: token.data,
      platform: 'ios',
    });
  } catch {
    // Token may be unavailable after permission was revoked.
  }
}
