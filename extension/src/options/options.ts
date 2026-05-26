import { getSettings, saveSettings } from '../shared/storage';

async function init() {
  const s = await getSettings();
  (document.getElementById('api-base-url') as HTMLInputElement).value = s.apiBaseUrl;
  (document.getElementById('quiz-per-day') as HTMLInputElement).value = String(s.quizPerDay);
  (document.getElementById('media-check-minutes') as HTMLInputElement).value = String(
    s.mediaCheckMinutes
  );
  (document.getElementById('notifications-enabled') as HTMLInputElement).checked =
    s.notificationsEnabled;

  document.getElementById('btn-save')?.addEventListener('click', async () => {
    await saveSettings({
      apiBaseUrl: (document.getElementById('api-base-url') as HTMLInputElement).value.trim(),
      quizPerDay: Number((document.getElementById('quiz-per-day') as HTMLInputElement).value),
      mediaCheckMinutes: Number(
        (document.getElementById('media-check-minutes') as HTMLInputElement).value
      ),
      notificationsEnabled: (document.getElementById('notifications-enabled') as HTMLInputElement)
        .checked,
    });
    const status = document.getElementById('status');
    if (status) status.textContent = 'Đã lưu. Alarm sẽ được cập nhật khi mở lại extension.';
  });
}

void init();
