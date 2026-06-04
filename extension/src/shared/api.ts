import { clearAuth, getAuth, getSettings } from './storage';
import type { DictionaryResult, MediaItem, QuizQuestion, Vocabulary } from './types';

class ApiError extends Error {
  constructor(
    message: string,
    public status: number
  ) {
    super(message);
  }
}

async function request<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const settings = await getSettings();
  const auth = await getAuth();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };

  if (auth.token) {
    headers.Authorization = `Bearer ${auth.token}`;
  }

  const res = await fetch(`${settings.apiBaseUrl}${path}`, {
    ...options,
    headers,
  });

  if (res.status === 401) {
    await clearAuth();
    throw new ApiError('Phiên đăng nhập hết hạn.', 401);
  }

  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new ApiError(
      (body as { message?: string }).message ?? 'Lỗi API',
      res.status
    );
  }

  return body as T;
}

export const api = {
  logout() {
    return request('/logout', { method: 'POST' });
  },

  lookup(word: string) {
    return request<DictionaryResult>(`/dictionary/${encodeURIComponent(word)}`);
  },

  listVocabularies() {
    return request<{ data: Vocabulary[] }>('/vocabularies');
  },

  saveVocabulary(payload: { word: string; phonetic?: string; meanings?: unknown[] }) {
    return request<{ data: Vocabulary }>('/vocabularies', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  deleteVocabulary(id: number) {
    return request(`/vocabularies/${id}`, { method: 'DELETE' });
  },

  listMedia() {
    return request<{ data: MediaItem[] }>('/media-items');
  },

  createMedia(payload: Omit<MediaItem, 'id' | 'next_listen_at'> & { notes?: string }) {
    return request<{ data: MediaItem }>('/media-items', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  createListeningMedia(payload: {
    title: string;
    type: 'youtube' | 'mp3' | 'audio';
    url?: string;
    language?: string;
    frequency?: 'daily' | 'weekly' | 'monthly';
    notes?: string;
    auto_process?: boolean;
  }) {
    return request<{ data: MediaItem; message?: string }>('/listening/media', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  deleteMedia(id: number) {
    return request(`/media-items/${id}`, { method: 'DELETE' });
  },

  dueMedia() {
    return request<{ data: MediaItem[] }>('/media-items/due');
  },

  markListened(id: number, snoozeOneHour = false) {
    return request(`/media-items/${id}/listened`, {
      method: 'POST',
      body: JSON.stringify({ snooze_one_hour: snoozeOneHour }),
    });
  },

  nextQuiz() {
    return request<{ data: QuizQuestion }>('/quiz/next');
  },

  submitQuizAttempt(payload: {
    vocabulary_id: number;
    question_type: string;
    correct: boolean;
  }) {
    return request('/quiz/attempts', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  sync() {
    return request<{
      vocabularies: Vocabulary[];
      media_items: MediaItem[];
      synced_at: string;
    }>('/sync');
  },
};

export { ApiError };
