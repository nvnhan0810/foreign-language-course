import { api, ApiError } from '../shared/api';
import { loginWithGoogle } from '../shared/googleAuth';
import {
  cacheSync,
  clearAuth,
  clearPendingQuiz,
  getAuth,
  getPendingQuiz,
} from '../shared/storage';
import type { DictionaryResult, MediaItem, QuizQuestion, Vocabulary } from '../shared/types';

let currentLookup: DictionaryResult | null = null;

const $ = (id: string) => document.getElementById(id)!;

async function init() {
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
  if (tab === 'media') void loadMedia();
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
    <strong>${escapeHtml(data.word)}</strong>
    ${data.phonetic ? `<div class="muted">${escapeHtml(data.phonetic)}</div>` : ''}
    ${meaningsHtml}
  `;
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
      <strong>${escapeHtml(v.word)}</strong>
      <div class="muted">${escapeHtml(def)}</div>
      <div class="muted">Quiz: ${v.times_quizzed} lần</div>
    </div>
    <button type="button" class="secondary">Xóa</button>
  `;
  li.querySelector('button')?.addEventListener('click', async () => {
    await api.deleteVocabulary(v.id);
    await loadVocab();
  });
  return li;
}

function bindMedia() {
  $('media-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target as HTMLFormElement;
    const fd = new FormData(form);
    try {
      await api.createMedia({
        title: fd.get('title') as string,
        url: fd.get('url') as string,
        type: fd.get('type') as 'audio' | 'youtube',
        frequency: fd.get('frequency') as 'daily' | 'weekly' | 'monthly',
        is_active: true,
      });
      form.reset();
      await loadMedia();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : 'Lỗi thêm media');
    }
  });
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
  li.className = 'item';
  const freqLabel =
    m.frequency === 'daily' ? 'ngày' : m.frequency === 'weekly' ? 'tuần' : 'tháng';
  li.innerHTML = `
    <div>
      <strong>${escapeHtml(m.title)}</strong>
      <div class="muted">${freqLabel} · ${m.type}</div>
      <a href="${escapeHtml(m.url)}" target="_blank">${escapeHtml(m.url)}</a>
    </div>
    <button type="button" class="secondary">Xóa</button>
  `;
  li.querySelector('button')?.addEventListener('click', async () => {
    await api.deleteMedia(m.id);
    await loadMedia();
  });
  return li;
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

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

void init();
