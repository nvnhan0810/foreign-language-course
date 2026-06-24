export interface Meaning {
  part_of_speech?: string | null;
  definition: string;
  example?: string | null;
}

export interface DictionaryResult {
  word: string;
  phonetic?: string | null;
  audio_url?: string | null;
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
  type: 'audio' | 'youtube' | 'mp3';
  frequency: 'daily' | 'weekly' | 'monthly';
  notes?: string | null;
  is_active: boolean;
  next_listen_at?: string | null;
  source_id?: string | null;
  analysis_status?: 'pending' | 'processing' | 'ready' | 'failed' | null;
  analysis_error?: string | null;
  analyzed_at?: string | null;
  language?: string;
}

export interface ListeningAssessmentSummary {
  id: number;
  type: 'quiz' | 'test' | 'exam';
  title: string;
  question_count: number;
  time_limit_minutes?: number | null;
  status: string;
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
