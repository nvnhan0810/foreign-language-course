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

  document.querySelectorAll('[data-dict-entry]').forEach((entry) => {
    entry.querySelectorAll('[data-dict-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        const name = tab.dataset.dictTab;
        entry.querySelectorAll('[data-dict-tab]').forEach((btn) => {
          const active = btn === tab;
          btn.classList.toggle('active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        entry.querySelectorAll('[data-dict-panel]').forEach((panel) => {
          const active = panel.dataset.dictPanel === name;
          panel.classList.toggle('active', active);
          panel.hidden = !active;
        });
      });
    });
  });

  const main = document.querySelector('.user-main[data-autostart-quiz="1"]');
  if (main && !document.querySelector('.quiz-prompt')) {
    const form = document.querySelector('form[action*="quiz/next"]');
    if (form) form.requestSubmit();
  }

  if (document.body.classList.contains('flc-app')) {
    document.documentElement.style.setProperty('--flc-app', '1');
    requestMobileFcmToken();
  }
})();
