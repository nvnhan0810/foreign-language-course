import type { DictionaryResult } from './types';

export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function renderDictionaryHtml(data: DictionaryResult, maxMeanings = 5): string {
  const meaningsHtml = data.meanings
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

  return `
    <div class="flc-word-head">
      <strong>${escapeHtml(data.word)}</strong>
      ${data.phonetic ? `<span class="flc-phonetic">${escapeHtml(data.phonetic)}</span>` : ''}
    </div>
    ${meaningsHtml}
  `;
}

/** Lấy từ để gọi API: 1 từ thì dùng luôn, câu thì lấy từ đầu tiên. */
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
