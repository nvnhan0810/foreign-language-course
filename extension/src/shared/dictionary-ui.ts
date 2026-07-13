import type { DictionaryResult, Meaning } from './types';

export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function playPronunciation(audioUrl: string | null | undefined, word: string): void {
  if (audioUrl) {
    const audio = new Audio(audioUrl);
    void audio.play().catch(() => speakWord(word));
    return;
  }

  speakWord(word);
}

export function speakWord(word: string): void {
  if (!word || !('speechSynthesis' in window)) return;
  const utterance = new SpeechSynthesisUtterance(word);
  utterance.lang = 'en-US';
  speechSynthesis.speak(utterance);
}

export function pronunciationButtonHtml(
  word: string,
  audioUrl?: string | null,
  className = 'flc-speak'
): string {
  return `<button type="button" class="${className}" data-audio="${audioUrl ? escapeHtml(audioUrl) : ''}" data-word="${escapeHtml(word)}" title="Pronounce" aria-label="Pronounce">🔊</button>`;
}

export function bindPronunciationButtons(root: ParentNode): void {
  root.querySelectorAll<HTMLButtonElement>('.flc-speak').forEach((btn) => {
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      playPronunciation(btn.dataset.audio || null, btn.dataset.word || '');
    });
  });
}

function collectRelated(data: DictionaryResult, key: 'synonyms' | 'antonyms'): string[] {
  const set = new Set<string>();
  for (const w of data[key] ?? []) {
    if (w.trim()) set.add(w.trim());
  }
  for (const m of data.meanings) {
    for (const w of m[key] ?? []) {
      if (w.trim()) set.add(w.trim());
    }
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b));
}

function meaningsHtml(meanings: Meaning[], maxMeanings: number): string {
  if (meanings.length === 0) {
    return `<p class="flc-empty muted">No detailed definitions yet.</p>`;
  }

  return meanings
    .slice(0, maxMeanings)
    .map(
      (m) => `
      <div class="flc-meaning">
        ${m.part_of_speech ? `<div class="flc-pos">${escapeHtml(m.part_of_speech)}</div>` : ''}
        <div class="flc-def">${escapeHtml(m.definition)}</div>
        ${m.example ? `<div class="flc-example">"${escapeHtml(m.example)}"</div>` : ''}
      </div>`
    )
    .join('');
}

function relatedHtml(words: string[], empty: string): string {
  if (words.length === 0) {
    return `<p class="flc-empty muted">${escapeHtml(empty)}</p>`;
  }
  return `<div class="flc-related-words">${words
    .map((w) => `<span class="flc-related-word">${escapeHtml(w)}</span>`)
    .join('')}</div>`;
}

export function renderDictionaryHtml(data: DictionaryResult, maxMeanings = 5): string {
  const synonyms = collectRelated(data, 'synonyms');
  const antonyms = collectRelated(data, 'antonyms');
  const uid = `flc-dict-${Math.random().toString(36).slice(2, 9)}`;

  return `
    <div class="flc-dict-entry" data-flc-dict>
      <div class="flc-word-head">
        <strong>${escapeHtml(data.word)}</strong>
        ${data.phonetic ? `<span class="flc-phonetic">${escapeHtml(data.phonetic)}</span>` : ''}
        ${pronunciationButtonHtml(data.word, data.audio_url)}
      </div>
      <div class="flc-dict-tabs" role="tablist" aria-label="Dictionary sections">
        <button type="button" class="flc-dict-tab active" role="tab" data-flc-tab="meanings" aria-selected="true">Meanings</button>
        <button type="button" class="flc-dict-tab" role="tab" data-flc-tab="synonyms" aria-selected="false">Synonyms</button>
        <button type="button" class="flc-dict-tab" role="tab" data-flc-tab="antonyms" aria-selected="false">Antonyms</button>
      </div>
      <div class="flc-dict-panel active" data-flc-panel="meanings" id="${uid}-meanings">
        ${meaningsHtml(data.meanings, maxMeanings)}
      </div>
      <div class="flc-dict-panel" data-flc-panel="synonyms" id="${uid}-synonyms" hidden>
        ${relatedHtml(synonyms, 'No synonyms found.')}
      </div>
      <div class="flc-dict-panel" data-flc-panel="antonyms" id="${uid}-antonyms" hidden>
        ${relatedHtml(antonyms, 'No antonyms found.')}
      </div>
    </div>
  `;
}

export function bindDictionaryTabs(root: ParentNode): void {
  root.querySelectorAll<HTMLElement>('[data-flc-dict]').forEach((entry) => {
    if (entry.dataset.tabsBound === '1') return;
    entry.dataset.tabsBound = '1';
    entry.querySelectorAll<HTMLButtonElement>('[data-flc-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        const name = tab.dataset.flcTab;
        entry.querySelectorAll<HTMLButtonElement>('[data-flc-tab]').forEach((btn) => {
          const active = btn === tab;
          btn.classList.toggle('active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        entry.querySelectorAll<HTMLElement>('[data-flc-panel]').forEach((panel) => {
          const active = panel.dataset.flcPanel === name;
          panel.classList.toggle('active', active);
          panel.hidden = !active;
        });
      });
    });
  });
}

/** Pick API lookup term: single word as-is, otherwise first word of the phrase. */
export function lookupTermFromSelection(text: string): string {
  const trimmed = normalizeSelection(text);
  const first = trimmed.split(/\s+/)[0] ?? trimmed;
  const word = first.replace(/^[^a-zA-Z]+|[^a-zA-Z'’-]+$/g, '');
  return word.toLowerCase();
}

export function normalizeSelection(text: string): string {
  return text
    .replace(/[\u200B-\u200D\uFEFF]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

export function isTranslatableSelection(text: string): boolean {
  const t = normalizeSelection(text);
  if (t.length < 1 || t.length > 400) return false;
  if (!/[a-zA-Z]/.test(t)) return false;
  const word = lookupTermFromSelection(t);
  return word.length >= 1 && word.length <= 48;
}
