import { api, ApiError } from '../shared/api';
import { escapeHtml, bindPronunciationButtons, pronunciationButtonHtml, playPronunciation } from '../shared/dictionary-ui';
import { loginWithGoogle } from '../shared/googleAuth';
import {
  cacheSync,
  clearAuth,
  clearPendingQuiz,
  getAuth,
  getPendingQuiz,
  getSettings,
  saveSettings,
} from '../shared/storage';
import { applyTheme, bindThemeToggleButtons, type ThemeMode } from '../shared/theme';
import { getActiveTabYouTubeInfo } from '../shared/youtube-tab';
import { mediaDifficultyClass, mediaDifficultyLabel } from '../shared/media-difficulty';
import type {
  DictionaryResult,
  ListeningQuestion,
  MediaItem,
  QuizQuestion,
  Vocabulary,
} from '../shared/types';

let currentLookup: DictionaryResult | null = null;

const $ = (id: string) => document.getElementById(id)!;

async function init() {
  const settings = await getSettings();
  applyTheme(settings.theme);

  let themeMode = settings.theme;
  bindThemeToggleButtons(
    () => themeMode,
    async (mode: ThemeMode) => {
      themeMode = mode;
      applyTheme(mode);
      await saveSettings({ theme: mode });
    }
  );

  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local' || !changes.settings) return;
    const next = changes.settings.newValue as { theme?: ThemeMode } | undefined;
    if (!next?.theme) return;
    themeMode = next.theme;
    applyTheme(next.theme);
  });

  bindTabs();
  bindAuth();
  bindLookup();
  bindMedia();
  bindQuiz();
  $('open-options').addEventListener('click', (e) => {
    e.preventDefault();
    chrome.runtime.openOptionsPage();
  });
  await refreshAuthUi();
  const { lookupWord } = await chrome.storage.local.get('lookupWord');
  if (lookupWord) {
    ($('lookup-input') as HTMLInputElement).value = lookupWord as string;
    await chrome.storage.local.remove('lookupWord');
    switchTab('lookup');
    await doLookup();
  }
  const pending = await getPendingQuiz<QuizQuestion>();
  if (pending) {
    switchTab('quiz');
    renderQuiz(pending);
  }
}

function bindTabs() {
  document.querySelectorAll('.tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      switchTab((btn as HTMLButtonElement).dataset.tab!);
    });
  });
}

function switchTab(tab: string) {
  document.querySelectorAll('.tabs button').forEach((b) => {
    b.classList.toggle('active', (b as HTMLButtonElement).dataset.tab === tab);
  });
  document.querySelectorAll('.tab-panel').forEach((p) => p.classList.add('hidden'));
  $(`${tab}-panel`).classList.remove('hidden');
  if (tab === 'vocab') void loadVocab();
  if (tab === 'media') {
    void loadMedia();
    void prefillMediaFromActiveTab();
  }
}

function bindAuth() {
  $('btn-google-login').addEventListener('click', () => void googleLogin());
  $('btn-logout').addEventListener('click', () => void logout());
}

async function refreshAuthUi() {
  const auth = await getAuth();
  const loggedIn = !!auth.token;
  $('auth-panel').classList.toggle('hidden', loggedIn);
  document.querySelector('.tabs')?.classList.toggle('hidden', !loggedIn);
  document.querySelector('footer')?.classList.toggle('hidden', !loggedIn);

  if (!loggedIn) {
    document.querySelectorAll('.tab-panel').forEach((p) => p.classList.add('hidden'));
    $('btn-logout').classList.add('hidden');
    return;
  }

  $('user-label').textContent = auth.userName ?? auth.email ?? '';
  $('btn-logout').classList.remove('hidden');
  switchTab('lookup');
  try {
    const sync = await api.sync();
    await cacheSync(sync);
    showExtensionNotice(sync.extension_notice);
  } catch {
    /* offline */
  }
}

function showExtensionNotice(notice?: string | null) {
  const el = $('extension-notice');
  const text = notice?.trim();
  if (!text) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = text;
  el.classList.remove('hidden');
}

async function googleLogin() {
  setAuthError('');
  const btn = $('btn-google-login') as HTMLButtonElement;
  btn.disabled = true;
  try {
    await loginWithGoogle();
    await refreshAuthUi();
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Đăng nhập Google thất bại.';
    if (chrome.runtime.lastError?.message) {
      setAuthError(chrome.runtime.lastError.message);
    } else {
      setAuthError(msg);
    }
  } finally {
    btn.disabled = false;
  }
}

async function logout() {
  try {
    await api.logout();
  } catch {
    /* ignore */
  }
  await clearAuth();
  await refreshAuthUi();
}

function setAuthError(msg: string) {
  $('auth-error').textContent = msg;
}

function bindLookup() {
  $('btn-lookup').addEventListener('click', () => void doLookup());
  $('btn-save-word').addEventListener('click', () => void saveWord());
  $('lookup-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') void doLookup();
  });
}

async function doLookup() {
  $('lookup-error').textContent = '';
  const word = ($('lookup-input') as HTMLInputElement).value.trim();
  if (!word) return;
  try {
    currentLookup = await api.lookup(word);
    renderLookup(currentLookup);
  } catch (e) {
    currentLookup = null;
    $('lookup-result').classList.add('hidden');
    $('btn-save-word').classList.add('hidden');
    $('lookup-error').textContent =
      e instanceof ApiError ? e.message : 'Không tra được từ.';
  }
}

function renderLookup(data: DictionaryResult) {
  const el = $('lookup-result');
  el.classList.remove('hidden');
  $('btn-save-word').classList.remove('hidden');
  const meaningsHtml = data.meanings
    .slice(0, 6)
    .map(
      (m) => `
      <div class="meaning">
        <div class="pos">${m.part_of_speech ?? ''}</div>
        <div>${escapeHtml(m.definition)}</div>
        ${m.example ? `<div class="example">"${escapeHtml(m.example)}"</div>` : ''}
      </div>`
    )
    .join('');
  el.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <strong>${escapeHtml(data.word)}</strong>
      ${pronunciationButtonHtml(data.word, data.audio_url, 'secondary flc-speak')}
    </div>
    ${data.phonetic ? `<div class="muted">${escapeHtml(data.phonetic)}</div>` : ''}
    ${meaningsHtml}
  `;
  bindPronunciationButtons(el);
}

async function saveWord() {
  if (!currentLookup) return;
  try {
    await api.saveVocabulary({
      word: currentLookup.word,
      phonetic: currentLookup.phonetic ?? undefined,
      meanings: currentLookup.meanings,
    });
    $('lookup-error').textContent = '';
    $('btn-save-word').textContent = 'Đã lưu ✓';
    setTimeout(() => {
      $('btn-save-word').textContent = 'Lưu từ';
    }, 2000);
  } catch (e) {
    $('lookup-error').textContent =
      e instanceof ApiError ? e.message : 'Không lưu được.';
  }
}

async function loadVocab() {
  const list = $('vocab-list');
  list.innerHTML = '';
  try {
    const { data } = await api.listVocabularies();
    $('vocab-empty').classList.toggle('hidden', data.length > 0);
    for (const v of data) {
      list.appendChild(renderVocabItem(v));
    }
  } catch {
    $('vocab-empty').textContent = 'Không tải được danh sách.';
  }
}

function renderVocabItem(v: Vocabulary) {
  const li = document.createElement('li');
  li.className = 'item';
  const def = v.meanings?.[0]?.definition ?? '';
  li.innerHTML = `
    <div>
      <div style="display:flex;align-items:center;gap:8px">
        <strong>${escapeHtml(v.word)}</strong>
        <button type="button" class="secondary flc-speak" data-word="${escapeHtml(v.word)}" title="Nghe phát âm">🔊</button>
      </div>
      <div class="muted">${escapeHtml(def)}</div>
      <div class="muted">Quiz: ${v.times_quizzed} lần</div>
    </div>
    <button type="button" class="secondary">Xóa</button>
  `;
  li.querySelector('.flc-speak')?.addEventListener('click', () => {
    void playVocabPronunciation(v.word);
  });
  li.querySelector('button')?.addEventListener('click', async () => {
    await api.deleteVocabulary(v.id);
    await loadVocab();
  });
  return li;
}

function bindMedia() {
  $('btn-prefill-youtube').addEventListener('click', () => void prefillMediaFromActiveTab());

  $('media-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target as HTMLFormElement;
    const fd = new FormData(form);
    const type = fd.get('type') as 'audio' | 'youtube';
    const frequency = fd.get('frequency') as 'daily' | 'weekly' | 'monthly';
    const difficulty = fd.get('difficulty') as 'beginner' | 'intermediate' | 'advanced';
    const payload = {
      title: fd.get('title') as string,
      url: fd.get('url') as string,
      frequency,
      difficulty,
      is_active: true,
    };

    try {
      if (type === 'youtube') {
        const res = await api.createListeningMedia({
          title: payload.title,
          url: payload.url,
          type: 'youtube',
          frequency: payload.frequency,
          difficulty: payload.difficulty,
          auto_process: true,
        });
        $('media-form-status').textContent =
          res.message ??
          'Đã lưu YouTube. Hệ thống phân tích và tạo ngân hàng câu hỏi — làm quiz trên tab Media khi sẵn sàng.';
      } else {
        await api.createMedia({
          ...payload,
          type: 'audio',
        });
        $('media-form-status').textContent = 'Đã thêm link audio.';
      }
      form.reset();
      await loadMedia();
    } catch (err) {
      $('media-form-status').textContent =
        err instanceof ApiError ? err.message : 'Lỗi thêm media';
    }
  });
}

async function prefillMediaFromActiveTab(): Promise<boolean> {
  const form = $('media-form') as HTMLFormElement;
  const titleInput = form.querySelector('[name="title"]') as HTMLInputElement;
  const urlInput = form.querySelector('[name="url"]') as HTMLInputElement;
  const typeSelect = form.querySelector('[name="type"]') as HTMLSelectElement;
  const statusEl = $('media-form-status');

  try {
    const info = await getActiveTabYouTubeInfo();
    if (!info) {
      statusEl.textContent =
        'Không lấy được video từ tab hiện tại. Mở trang video YouTube (watch/shorts) rồi bấm "Lấy từ tab YouTube".';
      return false;
    }

    titleInput.value = info.title;
    urlInput.value = info.url;
    typeSelect.value = 'youtube';
    statusEl.textContent = 'Đã điền tiêu đề và URL từ tab YouTube hiện tại.';
    return true;
  } catch {
    statusEl.textContent = 'Không đọc được tab YouTube. Thử reload extension rồi mở lại video.';
    return false;
  }
}

async function loadMedia() {
  const list = $('media-list');
  list.innerHTML = '';
  try {
    const { data } = await api.listMedia();
    for (const m of data) {
      list.appendChild(renderMediaItem(m));
    }
  } catch {
    /* ignore */
  }
}

function renderMediaItem(m: MediaItem) {
  const li = document.createElement('li');
  li.className = 'item media-item';
  const freqLabel =
    m.frequency === 'daily' ? 'ngày' : m.frequency === 'weekly' ? 'tuần' : 'tháng';
  const bankStatus = m.question_bank_status ?? 'pending';
  const bankCount = m.question_bank_count ?? 0;
  const bankReady = bankStatus === 'ready' && bankCount > 0;
  const difficulty = m.difficulty ?? 'intermediate';

  li.innerHTML = `
    <div class="media-item-main">
      <strong>${escapeHtml(m.title)}</strong>
      <div class="muted">
        <span class="${mediaDifficultyClass(difficulty)}">${escapeHtml(mediaDifficultyLabel(difficulty))}</span>
        · ${freqLabel} · ${m.type} · ngân hàng: ${bankStatus}${bankReady ? ` (${bankCount} câu)` : ''}
      </div>
      <a href="${escapeHtml(m.url)}" target="_blank">${escapeHtml(m.url)}</a>
      <div class="media-session-actions" data-media-id="${m.id}"></div>
    </div>
    <button type="button" class="secondary media-delete-btn">Xóa</button>
  `;

  const actionsEl = li.querySelector('.media-session-actions') as HTMLElement;
  if (bankReady) {
    void renderMediaSessionButtons(actionsEl, m.id);
  } else {
    actionsEl.innerHTML = `<span class="muted">Chưa có ngân hàng câu hỏi — đợi admin phân tích xong.</span>`;
  }

  li.querySelector('.media-delete-btn')?.addEventListener('click', async () => {
    await api.deleteMedia(m.id);
    hideListeningQuiz();
    await loadMedia();
  });
  return li;
}

async function renderMediaSessionButtons(container: HTMLElement, mediaId: number) {
  container.innerHTML = '<span class="muted">Đang tải...</span>';
  try {
    const { data } = await api.listListeningSessionOptions(mediaId);
    container.innerHTML = '';
    for (const option of data) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'secondary media-session-btn';
      btn.textContent = option.available
        ? `${option.type.toUpperCase()} (${option.question_count})`
        : `${option.type.toUpperCase()} (chưa đủ câu)`;
      btn.disabled = !option.available;
      btn.title = option.available
        ? `Random ${option.question_count} câu từ ngân hàng ${option.bank_count ?? 0} câu`
        : `Cần ${option.question_count} câu, hiện có ${option.bank_count ?? 0}`;
      btn.addEventListener('click', () => void startListeningQuiz(mediaId, option.type));
      container.appendChild(btn);
    }
  } catch (e) {
    container.innerHTML = `<span class="error">${e instanceof ApiError ? e.message : 'Không tải được quiz.'}</span>`;
  }
}

let activeListeningAssessmentId: number | null = null;
const listeningAnswers = new Map<number, string>();

function hideListeningQuiz() {
  activeListeningAssessmentId = null;
  listeningAnswers.clear();
  $('listening-quiz-area').classList.add('hidden');
  $('listening-quiz-area').innerHTML = '';
}

async function startListeningQuiz(mediaId: number, type: 'quiz' | 'test' | 'exam') {
  const area = $('listening-quiz-area');
  area.classList.remove('hidden');
  area.innerHTML = '<p class="muted">Đang tạo bài random...</p>';

  try {
    const { data } = await api.startListeningSession(mediaId, type);
    activeListeningAssessmentId = data.assessment_id;
    listeningAnswers.clear();
    renderListeningQuiz(data.title, data.questions, data.time_limit_minutes);
    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  } catch (e) {
    area.innerHTML = `<p class="error">${e instanceof ApiError ? e.message : 'Không bắt đầu được bài nghe.'}</p>`;
  }
}

function renderListeningQuiz(
  title: string,
  questions: ListeningQuestion[],
  timeLimitMinutes?: number | null
) {
  const area = $('listening-quiz-area');
  const timeNote =
    timeLimitMinutes != null ? `<p class="muted">Thời gian gợi ý: ${timeLimitMinutes} phút · câu hỏi random mỗi lần làm</p>` : '';

  area.innerHTML = `
    <div class="listening-quiz-header">
      <strong>${escapeHtml(title)}</strong>
      ${timeNote}
      <button type="button" id="btn-close-listening" class="link">Đóng</button>
    </div>
    <div id="listening-questions"></div>
    <button type="button" id="btn-submit-listening" class="primary">Nộp bài</button>
    <p id="listening-feedback" class="muted"></p>
  `;

  $('btn-close-listening').addEventListener('click', () => hideListeningQuiz());

  const list = $('listening-questions');
  questions.forEach((q, index) => {
    const block = document.createElement('div');
    block.className = 'listening-question';
    block.dataset.questionId = String(q.id);

    if (q.options && q.options.length > 0) {
      const optsHtml = q.options
        .map(
          (opt) =>
            `<label class="listening-option"><input type="radio" name="lq-${q.id}" value="${escapeHtml(opt)}" /> ${escapeHtml(opt)}</label>`
        )
        .join('');
      block.innerHTML = `<p><strong>Câu ${index + 1}</strong></p><p>${escapeHtml(q.prompt)}</p>${optsHtml}`;
      block.querySelectorAll('input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
          listeningAnswers.set(q.id, (input as HTMLInputElement).value);
        });
      });
    } else {
      block.innerHTML = `
        <p><strong>Câu ${index + 1}</strong></p>
        <p>${escapeHtml(q.prompt)}</p>
        <textarea rows="2" placeholder="Câu trả lời..." data-question-id="${q.id}"></textarea>
      `;
      block.querySelector('textarea')?.addEventListener('input', (e) => {
        listeningAnswers.set(q.id, (e.target as HTMLTextAreaElement).value.trim());
      });
    }

    list.appendChild(block);
  });

  $('btn-submit-listening').addEventListener('click', () => void submitListeningQuiz(questions.length));
}

async function submitListeningQuiz(total: number) {
  if (!activeListeningAssessmentId) return;

  if (listeningAnswers.size < total) {
    $('listening-feedback').textContent = 'Trả lời hết các câu trước khi nộp.';
    return;
  }

  const answers = [...listeningAnswers.entries()].map(([question_id, answer]) => ({
    question_id,
    answer,
  }));

  $('btn-submit-listening').setAttribute('disabled', 'true');
  $('listening-feedback').textContent = 'Đang chấm...';

  try {
    const { data } = await api.submitListeningAttempt(activeListeningAssessmentId, answers);
    const lines = data.results.map((r) => {
      const mark = r.correct ? '✓' : '✗';
      const explain = r.explanation ? ` — ${escapeHtml(r.explanation)}` : '';
      return `<div class="${r.correct ? 'correct' : 'wrong'}">${mark} ${escapeHtml(r.answer)}${explain}</div>`;
    });

    $('listening-questions').innerHTML = `
      <p><strong>Kết quả: ${data.score}/${data.total} (${data.percentage}%) — ${data.passed ? 'Đạt' : 'Chưa đạt'}</strong></p>
      ${lines.join('')}
    `;
    $('btn-submit-listening').classList.add('hidden');
    $('listening-feedback').textContent = 'Bấm Quiz/Test/Exam lại để làm bộ câu random mới.';
    activeListeningAssessmentId = null;
    listeningAnswers.clear();
  } catch (e) {
    $('listening-feedback').textContent =
      e instanceof ApiError ? e.message : 'Không nộp được bài.';
    $('btn-submit-listening').removeAttribute('disabled');
  }
}

function bindQuiz() {
  $('btn-next-quiz').addEventListener('click', () => void fetchQuiz());
}

async function fetchQuiz() {
  try {
    const { data } = await api.nextQuiz();
    await clearPendingQuiz();
    renderQuiz(data);
  } catch (e) {
    $('quiz-area').innerHTML = `<p class="error">${
      e instanceof ApiError ? e.message : 'Không lấy được câu hỏi.'
    }</p><button type="button" id="btn-next-quiz">Thử lại</button>`;
    $('btn-next-quiz')?.addEventListener('click', () => void fetchQuiz());
  }
}

function renderQuiz(q: QuizQuestion) {
  const area = $('quiz-area');
  area.innerHTML = `
    <p><strong>${q.question_type === 'word_to_definition' ? 'Chọn nghĩa đúng' : 'Chọn từ đúng'}</strong></p>
    <p>${escapeHtml(q.prompt)}</p>
    <div id="quiz-options"></div>
    <p id="quiz-feedback" class="muted"></p>
    <button type="button" id="btn-next-quiz" class="secondary">Câu tiếp</button>
  `;
  const opts = $('quiz-options');
  for (const opt of q.options) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'quiz-option';
    btn.textContent = opt;
    btn.addEventListener('click', () => void answerQuiz(q, opt, btn));
    opts.appendChild(btn);
  }
  $('btn-next-quiz').addEventListener('click', () => void fetchQuiz());
}

async function answerQuiz(q: QuizQuestion, chosen: string, btn: HTMLButtonElement) {
  const correct = chosen === q.correct_answer;
  btn.classList.add(correct ? 'correct' : 'wrong');
  document.querySelectorAll('.quiz-option').forEach((b) => {
    (b as HTMLButtonElement).disabled = true;
    if ((b as HTMLButtonElement).textContent === q.correct_answer) {
      b.classList.add('correct');
    }
  });
  $('quiz-feedback').textContent = correct ? 'Chính xác!' : `Đáp án: ${q.correct_answer}`;
  await api.submitQuizAttempt({
    vocabulary_id: q.vocabulary_id,
    question_type: q.question_type,
    correct,
  });
}

async function playVocabPronunciation(word: string): Promise<void> {
  try {
    const data = await api.lookup(word);
    playPronunciation(data.audio_url, data.word);
  } catch {
    playPronunciation(null, word);
  }
}

void init();
