export const YOUTUBE_CONTEXT_KEY = 'flc_youtube_context';

export function normalizeYouTubeUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    let host = parsed.hostname.toLowerCase();
    host = host.replace(/^www\./, '').replace(/^m\./, '');

    if (host === 'youtu.be') {
      const id = parsed.pathname.slice(1).split('/')[0];
      return id && id.length >= 11 ? `https://www.youtube.com/watch?v=${id.slice(0, 11)}` : null;
    }

    if (host === 'youtube.com' || host.endsWith('.youtube.com')) {
      const watchId = parsed.searchParams.get('v');
      if (parsed.pathname === '/watch' && watchId) {
        return `https://www.youtube.com/watch?v=${watchId.slice(0, 11)}`;
      }

      const shortsMatch = parsed.pathname.match(/^\/shorts\/([a-zA-Z0-9_-]{11})/);
      if (shortsMatch) {
        return `https://www.youtube.com/watch?v=${shortsMatch[1]}`;
      }
    }
  } catch {
    return null;
  }

  return null;
}

export function cleanYouTubeTitle(title: string): string {
  return title
    .replace(/\s*[-–|]\s*YouTube(?: Music)?\s*$/i, '')
    .trim();
}

export function extractYouTubeFromPage(): { title: string; url: string } | null {
  const href = window.location.href;
  const parsed = new URL(href);
  let host = parsed.hostname.toLowerCase().replace(/^www\./, '').replace(/^m\./, '');

  let videoId: string | null = null;
  if (host === 'youtu.be') {
    videoId = parsed.pathname.slice(1).split('/')[0]?.slice(0, 11) ?? null;
  } else if (host.includes('youtube.com')) {
    videoId =
      parsed.searchParams.get('v')?.slice(0, 11) ??
      parsed.pathname.match(/^\/shorts\/([a-zA-Z0-9_-]{11})/)?.[1] ??
      null;
  }

  if (!videoId) {
    return null;
  }

  const url = `https://www.youtube.com/watch?v=${videoId}`;
  const metaTitle = document.querySelector('meta[property="og:title"]')?.getAttribute('content');
  const h1 =
    document.querySelector('h1 yt-formatted-string')?.textContent?.trim() ??
    document.querySelector('h1.ytd-watch-metadata yt-formatted-string')?.textContent?.trim();
  const rawTitle = metaTitle || h1 || document.title || 'YouTube video';
  const title = rawTitle.replace(/\s*[-–|]\s*YouTube(?: Music)?\s*$/i, '').trim() || 'YouTube video';

  return { title, url };
}

export async function getActiveTabYouTubeInfo(): Promise<{
  title: string;
  url: string;
} | null> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (tab?.id) {
    try {
      const [result] = await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        func: extractYouTubeFromPage,
      });
      if (result?.result?.url) {
        return result.result;
      }
    } catch {
      /* fall through */
    }
  }

  const stored = await chrome.storage.local.get(YOUTUBE_CONTEXT_KEY);
  const ctx = stored[YOUTUBE_CONTEXT_KEY] as
    | { title?: string; url?: string; updatedAt?: number }
    | undefined;

  if (ctx?.url && normalizeYouTubeUrl(ctx.url)) {
    const tabUrl = tab?.url ? normalizeYouTubeUrl(tab.url) : null;
    const ctxUrl = normalizeYouTubeUrl(ctx.url);
    if (!tabUrl || tabUrl === ctxUrl) {
      return {
        title: ctx.title ?? 'YouTube video',
        url: ctxUrl!,
      };
    }
  }

  if (tab?.url) {
    const url = normalizeYouTubeUrl(tab.url);
    if (url) {
      return {
        title: cleanYouTubeTitle(tab.title ?? '') || 'YouTube video',
        url,
      };
    }
  }

  return null;
}
