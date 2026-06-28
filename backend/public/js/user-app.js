(() => {
  'use strict';

  const FCM_TOKEN_EVENT = 'flc:fcm-token';

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

  if (document.body.classList.contains('flc-app')) {
    document.documentElement.style.setProperty('--flc-app', '1');
    requestMobileFcmToken();
  }
})();
