import { normalizeApiBaseUrl } from './config';

export function apiHostPattern(apiBaseUrl: string): string {
  const origin = new URL(normalizeApiBaseUrl(apiBaseUrl)).origin;
  return `${origin}/*`;
}

/** Xin quyền host nếu domain API chưa có trong manifest. */
export async function ensureHostPermissionForApi(
  apiBaseUrl: string
): Promise<{ ok: boolean; message?: string }> {
  try {
    const pattern = apiHostPattern(apiBaseUrl);
    const has = await chrome.permissions.contains({ origins: [pattern] });
    if (has) return { ok: true };

    const granted = await chrome.permissions.request({ origins: [pattern] });
    if (!granted) {
      return {
        ok: false,
        message:
          'Cần cho phép extension truy cập domain API. Nếu từ chối, các tính năng đồng bộ sẽ không hoạt động.',
      };
    }
    return { ok: true };
  } catch {
    return { ok: false, message: 'Địa chỉ API không hợp lệ (ví dụ: flc.example.com hoặc https://flc.example.com/api).' };
  }
}
