import { apiOrigin } from './config';
import { getSettings, saveAuth } from './storage';

export async function loginWithGoogle(): Promise<void> {
  const settings = await getSettings();
  const redirectUri = chrome.identity.getRedirectURL();
  const startUrl =
    `${apiOrigin(settings.apiBaseUrl)}/api/auth/google/redirect?` +
    `redirect_uri=${encodeURIComponent(redirectUri)}`;

  const responseUrl = await chrome.identity.launchWebAuthFlow({
    url: startUrl,
    interactive: true,
  });

  if (!responseUrl) {
    throw new Error('Đăng nhập bị hủy.');
  }

  const parsed = new URL(responseUrl);
  const error = parsed.searchParams.get('error');
  if (error) {
    throw new Error(error);
  }

  const token = parsed.searchParams.get('token');
  if (!token) {
    throw new Error('Không nhận được token từ server.');
  }

  await saveAuth({
    token,
    email: parsed.searchParams.get('email'),
    userName: parsed.searchParams.get('name'),
  });
}
