import type { AuthState, UserSettings } from './types';

const DEFAULT_SETTINGS: UserSettings = {
  apiBaseUrl: 'http://localhost:8080/api',
  quizPerDay: 2,
  mediaCheckMinutes: 30,
  notificationsEnabled: true,
};

export async function getSettings(): Promise<UserSettings> {
  const { settings } = await chrome.storage.local.get('settings');
  return { ...DEFAULT_SETTINGS, ...(settings as UserSettings | undefined) };
}

export async function saveSettings(settings: Partial<UserSettings>): Promise<void> {
  const current = await getSettings();
  await chrome.storage.local.set({ settings: { ...current, ...settings } });
}

export async function getAuth(): Promise<AuthState> {
  const { auth } = await chrome.storage.local.get('auth');
  return (auth as AuthState) ?? { token: null, userName: null, email: null };
}

export async function saveAuth(auth: AuthState): Promise<void> {
  await chrome.storage.local.set({ auth });
}

export async function clearAuth(): Promise<void> {
  await chrome.storage.local.remove('auth');
}

export async function cacheSync(data: unknown): Promise<void> {
  await chrome.storage.local.set({ lastSync: data, syncedAt: new Date().toISOString() });
}

export async function getCachedSync<T>(): Promise<T | null> {
  const { lastSync } = await chrome.storage.local.get('lastSync');
  return (lastSync as T) ?? null;
}

export async function setPendingQuiz(question: unknown): Promise<void> {
  await chrome.storage.local.set({ pendingQuiz: question });
}

export async function getPendingQuiz<T>(): Promise<T | null> {
  const { pendingQuiz } = await chrome.storage.local.get('pendingQuiz');
  return (pendingQuiz as T) ?? null;
}

export async function clearPendingQuiz(): Promise<void> {
  await chrome.storage.local.remove('pendingQuiz');
}
