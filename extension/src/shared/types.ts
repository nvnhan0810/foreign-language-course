export interface Meaning {
  part_of_speech?: string | null;
  definition: string;
  example?: string | null;
}

export interface DictionaryResult {
  word: string;
  phonetic?: string | null;
  meanings: Meaning[];
  source?: string;
}

export interface Vocabulary {
  id: number;
  word: string;
  phonetic?: string | null;
  meanings: Meaning[];
  times_quizzed: number;
  last_quizzed_at?: string | null;
  examples?: { id: number; example: string; definition_ref?: string | null }[];
}

export interface MediaItem {
  id: number;
  title: string;
  url: string;
  type: 'audio' | 'youtube';
  frequency: 'daily' | 'weekly' | 'monthly';
  notes?: string | null;
  is_active: boolean;
  next_listen_at?: string | null;
}

export interface QuizQuestion {
  vocabulary_id: number;
  question_type: string;
  prompt: string;
  options: string[];
  correct_answer: string;
}

export interface UserSettings {
  apiBaseUrl: string;
  quizPerDay: number;
  mediaCheckMinutes: number;
  notificationsEnabled: boolean;
}

export interface AuthState {
  token: string | null;
  userName: string | null;
  email: string | null;
}
