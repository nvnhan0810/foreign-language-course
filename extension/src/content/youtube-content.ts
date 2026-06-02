import {
  cleanYouTubeTitle,
  normalizeYouTubeUrl,
  YOUTUBE_CONTEXT_KEY,
} from '../shared/youtube-tab';

function syncYouTubeContext(): void {
  const url = normalizeYouTubeUrl(location.href);
  if (!url) {
    return;
  }

  const metaTitle = document.querySelector('meta[property="og:title"]')?.getAttribute('content');
  const h1 =
    document.querySelector('h1 yt-formatted-string')?.textContent?.trim() ??
    document.querySelector('h1.ytd-watch-metadata yt-formatted-string')?.textContent?.trim();
  const title = cleanYouTubeTitle(metaTitle || h1 || document.title) || 'YouTube video';

  void chrome.storage.local.set({
    [YOUTUBE_CONTEXT_KEY]: {
      title,
      url,
      updatedAt: Date.now(),
    },
  });
}

function scheduleSync(delayMs = 600): void {
  window.setTimeout(syncYouTubeContext, delayMs);
}

syncYouTubeContext();
scheduleSync(1500);

let lastHref = location.href;
window.setInterval(() => {
  if (location.href !== lastHref) {
    lastHref = location.href;
    scheduleSync();
  }
}, 800);

window.addEventListener('yt-navigate-finish', () => scheduleSync());

const observer = new MutationObserver(() => {
  if (location.href !== lastHref) {
    lastHref = location.href;
    scheduleSync();
  }
});

observer.observe(document.documentElement, { childList: true, subtree: true });
