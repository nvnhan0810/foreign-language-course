(() => {
  'use strict';

  const FCM_TOKEN_EVENT = 'flc:fcm-token';
  const THEME_STORAGE_KEY = 'flc-theme';

  function getStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
  }

  function resolveTheme(mode) {
    if (mode === 'dark') return 'dark';
    if (mode === 'light') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function updateThemeColor(resolved) {
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', resolved === 'dark' ? '#1a2332' : '#4361ee');
    }
  }

  function applyTheme(mode) {
    const resolved = resolveTheme(mode);
    document.documentElement.dataset.theme = resolved;
    updateThemeColor(resolved);

    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
      button.classList.toggle('active', button.dataset.themeChoice === mode);
    });
  }

  function initTheme() {
    const mode = getStoredTheme();
    applyTheme(mode);

    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
      button.addEventListener('click', () => {
        const choice = button.dataset.themeChoice;
        if (!choice) return;
        localStorage.setItem(THEME_STORAGE_KEY, choice);
        applyTheme(choice);
      });
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (getStoredTheme() === 'system') {
        applyTheme('system');
      }
    });
  }

  initTheme();

  function initMediaDifficultyFilter() {
    const filters = document.querySelectorAll('[data-difficulty-filter]');
    const items = document.querySelectorAll('[data-media-list] .list-item[data-difficulty]');
    const emptyState = document.querySelector('.media-filter-empty');
    if (!filters.length || !items.length) return;

    const applyFilter = (value) => {
      let visible = 0;
      items.forEach((item) => {
        const show = value === 'all' || item.dataset.difficulty === value;
        item.hidden = !show;
        if (show) visible++;
      });
      if (emptyState) {
        emptyState.hidden = visible > 0;
      }
      filters.forEach((button) => {
        button.classList.toggle('active', button.dataset.difficultyFilter === value);
      });
    };

    filters.forEach((button) => {
      button.addEventListener('click', () => {
        applyFilter(button.dataset.difficultyFilter || 'all');
      });
    });
  }

  initMediaDifficultyFilter();

  function initYouTubeAdd() {
    const root = document.querySelector('[data-youtube-add]');
    if (!root) return;

    const previewUrl = root.dataset.previewUrl;
    const urlInput = root.querySelector('[data-youtube-url]');
    const fetchBtn = root.querySelector('[data-youtube-fetch]');
    const statusEl = root.querySelector('[data-youtube-status]');
    const previewEl = root.querySelector('[data-youtube-preview]');
    const thumbEl = root.querySelector('[data-youtube-thumb]');
    const titleInput = root.querySelector('[data-youtube-title]');
    const hiddenUrl = root.querySelector('[data-youtube-url-hidden]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (!previewUrl || !urlInput || !fetchBtn || !previewEl || !titleInput || !hiddenUrl) {
      return;
    }

    const setStatus = (message, isError = false) => {
      if (!statusEl) return;
      if (!message) {
        statusEl.hidden = true;
        statusEl.textContent = '';
        statusEl.classList.remove('is-error');
        return;
      }
      statusEl.hidden = false;
      statusEl.textContent = message;
      statusEl.classList.toggle('is-error', isError);
    };

    const showPreview = (data) => {
      hiddenUrl.value = data.url || '';
      titleInput.value = data.title || '';
      if (thumbEl) {
        if (data.thumbnail_url) {
          thumbEl.src = data.thumbnail_url;
          thumbEl.alt = data.title || 'YouTube thumbnail';
          thumbEl.hidden = false;
        } else {
          thumbEl.hidden = true;
          thumbEl.removeAttribute('src');
        }
      }
      previewEl.hidden = false;
      titleInput.focus();
      titleInput.select();
    };

    const fetchPreview = async () => {
      const url = urlInput.value.trim();
      if (!url) {
        setStatus('Paste a YouTube URL first.', true);
        urlInput.focus();
        return;
      }

      fetchBtn.disabled = true;
      setStatus('Fetching video info…');

      try {
        const res = await fetch(previewUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ url }),
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
          const message =
            payload.message ||
            (payload.errors && payload.errors.url && payload.errors.url[0]) ||
            'Could not fetch this YouTube URL.';
          setStatus(message, true);
          previewEl.hidden = true;
          return;
        }

        showPreview(payload.data || {});
        const author = payload.data?.author_name;
        setStatus(author ? `Found · ${author}` : 'Found. Edit the title if you want, then save.');
      } catch {
        setStatus('Network error while fetching YouTube info.', true);
      } finally {
        fetchBtn.disabled = false;
      }
    };

    fetchBtn.addEventListener('click', () => void fetchPreview());
    urlInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        void fetchPreview();
      }
    });
  }

  initYouTubeAdd();

  function initClearableInputs() {
    document.querySelectorAll('.input-with-clear').forEach((wrap) => {
      const input = wrap.querySelector('input');
      const btn = wrap.querySelector('.input-clear');
      if (!input || !btn) return;

      const sync = () => {
        btn.hidden = input.value.trim().length === 0;
      };

      input.addEventListener('input', sync);
      sync();

      btn.addEventListener('click', () => {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
        sync();
      });
    });
  }

  initClearableInputs();

  function initVocabSearch() {
    const input = document.getElementById('vocab-search');
    const items = document.querySelectorAll('[data-vocab-list] .vocab-card[data-vocab-search]');
    const emptyState = document.querySelector('.vocab-search-empty');
    if (!input || !items.length) return;

    const apply = () => {
      const q = input.value.trim().toLowerCase();
      let visible = 0;
      items.forEach((item) => {
        const haystack = item.dataset.vocabSearch || '';
        const show = q === '' || haystack.includes(q);
        item.hidden = !show;
        if (show) visible++;
      });
      if (emptyState) {
        emptyState.hidden = visible > 0;
      }
    };

    input.addEventListener('input', apply);
  }

  initVocabSearch();

  function speakWord(word) {
    if (!word || !('speechSynthesis' in window)) return;
    const utterance = new SpeechSynthesisUtterance(word);
    utterance.lang = 'en-US';
    speechSynthesis.speak(utterance);
  }

  async function playPronunciation(btn) {
    const audioUrl = btn.dataset.audio;
    const pronounceUrl = btn.dataset.pronounceUrl;
    const word = btn.dataset.word || '';

    if (audioUrl) {
      try {
        await new Audio(audioUrl).play();
        return;
      } catch (_) {
        speakWord(word);
        return;
      }
    }

    if (pronounceUrl) {
      try {
        const res = await fetch(pronounceUrl, {
          headers: { Accept: 'application/json' },
        });
        if (res.ok) {
          const data = await res.json();
          if (data.audio_url) {
            await new Audio(data.audio_url).play();
            return;
          }
        }
      } catch (_) {}
    }

    speakWord(word);
  }

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  let lastSavedPushToken = '';

  async function savePushToken(token, platform) {
    const trimmed = (token || '').trim();
    if (!trimmed || !platform) return;
    if (trimmed === lastSavedPushToken) return;

    const csrf = csrfToken();
    if (!csrf) return;

    try {
      const res = await fetch('/home/push-token', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ token: trimmed, platform }),
      });
      if (res.ok) {
        lastSavedPushToken = trimmed;
      }
    } catch (_) {}
  }

  function requestMobileFcmToken() {
    if (!window.FlcNative) return;
    FlcNative.postMessage(JSON.stringify({ type: 'request-fcm-token' }));
  }

  window.addEventListener(FCM_TOKEN_EVENT, (event) => {
    const detail = event.detail;
    if (!detail || typeof detail !== 'object') return;
    savePushToken(detail.token, detail.platform);
  });

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('.flc-pronounce');
    if (btn) {
      event.preventDefault();
      event.stopPropagation();
      playPronunciation(btn);
      return;
    }

    const trigger = event.target.closest('.action-menu-trigger');
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      const menu = trigger.closest('.action-menu');
      const panel = menu?.querySelector('.action-menu-panel');
      if (!panel) return;
      const willOpen = panel.hidden;
      document.querySelectorAll('.action-menu-panel').forEach((p) => {
        p.hidden = true;
      });
      panel.hidden = !willOpen;
      return;
    }

    if (!event.target.closest('.action-menu')) {
      document.querySelectorAll('.action-menu-panel').forEach((p) => {
        p.hidden = true;
      });
    }
  });

  document.querySelectorAll('.flc-form-submit').forEach((form) => {
    form.addEventListener('submit', () => {
      const submit = form.querySelector('[type="submit"]');
      if (submit && !submit.disabled) {
        submit.disabled = true;
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Đang xử lý...';
      }
    });
  });

  document.querySelectorAll('[data-transcript]').forEach((container) => {
    const view = container.querySelector('[data-transcript-view]');
    const form = container.querySelector('[data-transcript-form]');
    const editBtn = container.querySelector('[data-transcript-edit]');
    const cancelBtn = container.querySelector('[data-transcript-cancel]');
    if (!view || !form) return;

    const showForm = () => {
      view.hidden = true;
      form.hidden = false;
      form.querySelector('textarea')?.focus();
    };
    const showView = () => {
      form.hidden = true;
      view.hidden = false;
    };

    editBtn?.addEventListener('click', showForm);
    cancelBtn?.addEventListener('click', showView);
  });

  document.querySelectorAll('.choice-card input').forEach((input) => {
    input.addEventListener('change', () => {
      const group = input.closest('.choice-group');
      if (!group) return;
      group.querySelectorAll('.choice-card').forEach((card) => card.classList.remove('selected'));
      input.closest('.choice-card')?.classList.add('selected');
    });
  });

  const main = document.querySelector('.user-main[data-autostart-quiz="1"]');
  if (main && !document.querySelector('.quiz-prompt')) {
    const form = document.querySelector('form[action*="quiz/next"]');
    if (form) form.requestSubmit();
  }

  document.querySelectorAll('[data-copy-token]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const value = document.getElementById('agent-token-value')?.textContent?.trim();
      if (!value) return;
      try {
        await navigator.clipboard.writeText(value);
        btn.textContent = 'Copied';
        setTimeout(() => {
          btn.textContent = 'Copy';
        }, 1500);
      } catch {
        btn.textContent = 'Copy failed';
      }
    });
  });

  if (document.body.classList.contains('flc-app')) {
    document.documentElement.style.setProperty('--flc-app', '1');
    requestMobileFcmToken();
  }
})();
