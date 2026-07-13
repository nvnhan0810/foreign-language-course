class Meaning {
  Meaning({
    this.partOfSpeech,
    required this.definition,
    this.example,
    this.examples = const [],
    this.synonyms = const [],
    this.antonyms = const [],
  });

  final String? partOfSpeech;
  final String definition;
  final String? example;
  final List<String> examples;
  final List<String> synonyms;
  final List<String> antonyms;

  factory Meaning.fromJson(Map<String, dynamic> json) {
    final examples = (json['examples'] as List<dynamic>? ?? [])
        .whereType<String>()
        .toList();
    final legacyExample = json['example'] as String?;

    return Meaning(
      partOfSpeech: json['part_of_speech'] as String?,
      definition: json['definition'] as String? ?? '',
      example: legacyExample ?? (examples.isNotEmpty ? examples.first : null),
      examples: examples.isNotEmpty
          ? examples
          : (legacyExample != null ? [legacyExample] : const []),
      synonyms:
          (json['synonyms'] as List<dynamic>? ?? []).whereType<String>().toList(),
      antonyms:
          (json['antonyms'] as List<dynamic>? ?? []).whereType<String>().toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        if (partOfSpeech != null) 'part_of_speech': partOfSpeech,
        'definition': definition,
        if (example != null) 'example': example,
        if (examples.isNotEmpty) 'examples': examples,
        if (synonyms.isNotEmpty) 'synonyms': synonyms,
        if (antonyms.isNotEmpty) 'antonyms': antonyms,
      };
}

class DictionaryResult {
  DictionaryResult({
    required this.word,
    this.phonetic,
    this.audioUrl,
    required this.meanings,
    this.synonyms = const [],
    this.antonyms = const [],
    this.source,
    this.curated = false,
  });

  final String word;
  final String? phonetic;
  final String? audioUrl;
  final List<Meaning> meanings;
  final List<String> synonyms;
  final List<String> antonyms;
  final String? source;
  final bool curated;

  factory DictionaryResult.fromJson(Map<String, dynamic> json) => DictionaryResult(
        word: json['word'] as String? ?? '',
        phonetic: json['phonetic'] as String?,
        audioUrl: json['audio_url'] as String?,
        meanings: (json['meanings'] as List<dynamic>? ?? [])
            .map((e) => Meaning.fromJson(e as Map<String, dynamic>))
            .toList(),
        synonyms:
            (json['synonyms'] as List<dynamic>? ?? []).whereType<String>().toList(),
        antonyms:
            (json['antonyms'] as List<dynamic>? ?? []).whereType<String>().toList(),
        source: json['source'] as String?,
        curated: json['curated'] as bool? ?? false,
      );
}

class Vocabulary {
  Vocabulary({
    required this.id,
    required this.word,
    this.phonetic,
    required this.meanings,
    required this.timesQuizzed,
    this.lastQuizzedAt,
  });

  final int id;
  final String word;
  final String? phonetic;
  final List<Meaning> meanings;
  final int timesQuizzed;
  final String? lastQuizzedAt;

  factory Vocabulary.fromJson(Map<String, dynamic> json) => Vocabulary(
        id: json['id'] as int,
        word: json['word'] as String? ?? '',
        phonetic: json['phonetic'] as String?,
        meanings: (json['meanings'] as List<dynamic>? ?? [])
            .map((e) => Meaning.fromJson(e as Map<String, dynamic>))
            .toList(),
        timesQuizzed: json['times_quizzed'] as int? ?? 0,
        lastQuizzedAt: json['last_quizzed_at'] as String?,
      );
}

class MediaItem {
  MediaItem({
    required this.id,
    required this.title,
    required this.url,
    required this.type,
    required this.frequency,
    this.difficulty = 'intermediate',
    this.notes,
    this.transcript,
    required this.isActive,
    this.nextListenAt,
    this.sourceId,
    this.analysisStatus,
    this.questionBankStatus,
    this.questionBankCount,
  });

  final int id;
  final String title;
  final String url;
  final String type;
  final String frequency;
  final String difficulty;
  final String? notes;
  final String? transcript;
  final bool isActive;
  final String? nextListenAt;
  final String? sourceId;
  final String? analysisStatus;
  final String? questionBankStatus;
  final int? questionBankCount;

  String get difficultyLabel => switch (difficulty) {
        'beginner' => 'Beginner',
        'advanced' => 'Advanced',
        _ => 'Intermediate',
      };

  bool get isYoutube => type == 'youtube';

  bool get isQuestionBankReady =>
      questionBankStatus == 'ready' && (questionBankCount ?? 0) > 0;

  factory MediaItem.fromJson(Map<String, dynamic> json) => MediaItem(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        url: json['url'] as String? ?? '',
        type: json['type'] as String? ?? 'audio',
        frequency: json['frequency'] as String? ?? 'weekly',
        difficulty: json['difficulty'] as String? ?? 'intermediate',
        notes: json['notes'] as String?,
        transcript: json['transcript'] as String?,
        isActive: json['is_active'] as bool? ?? true,
        nextListenAt: json['next_listen_at'] as String?,
        sourceId: json['source_id'] as String?,
        analysisStatus: json['analysis_status'] as String?,
        questionBankStatus: json['question_bank_status'] as String?,
        questionBankCount: json['question_bank_count'] as int?,
      );
}

class ListeningSessionOption {
  ListeningSessionOption({
    required this.type,
    required this.title,
    required this.questionCount,
    this.timeLimitMinutes,
    required this.available,
    this.bankCount,
    this.bankStatus,
  });

  final String type;
  final String title;
  final int questionCount;
  final int? timeLimitMinutes;
  final bool available;
  final int? bankCount;
  final String? bankStatus;

  factory ListeningSessionOption.fromJson(Map<String, dynamic> json) =>
      ListeningSessionOption(
        type: json['type'] as String? ?? 'quiz',
        title: json['title'] as String? ?? '',
        questionCount: json['question_count'] as int? ?? 0,
        timeLimitMinutes: json['time_limit_minutes'] as int?,
        available: json['available'] as bool? ?? false,
        bankCount: json['bank_count'] as int?,
        bankStatus: json['bank_status'] as String?,
      );
}

class ListeningQuestion {
  ListeningQuestion({
    required this.id,
    required this.order,
    required this.questionType,
    required this.prompt,
    this.options,
    this.audioStartSeconds,
    this.audioEndSeconds,
  });

  final int id;
  final int order;
  final String questionType;
  final String prompt;
  final List<String>? options;
  final int? audioStartSeconds;
  final int? audioEndSeconds;

  factory ListeningQuestion.fromJson(Map<String, dynamic> json) => ListeningQuestion(
        id: json['id'] as int,
        order: json['order'] as int? ?? 0,
        questionType: json['question_type'] as String? ?? '',
        prompt: json['prompt'] as String? ?? '',
        options: (json['options'] as List<dynamic>?)
            ?.map((e) => e.toString())
            .toList(),
        audioStartSeconds: json['audio_start_seconds'] as int?,
        audioEndSeconds: json['audio_end_seconds'] as int?,
      );
}

class QuizQuestion {
  QuizQuestion({
    required this.vocabularyId,
    required this.questionType,
    required this.prompt,
    required this.options,
    required this.correctAnswer,
  });

  final int vocabularyId;
  final String questionType;
  final String prompt;
  final List<String> options;
  final String correctAnswer;

  factory QuizQuestion.fromJson(Map<String, dynamic> json) => QuizQuestion(
        vocabularyId: json['vocabulary_id'] as int,
        questionType: json['question_type'] as String? ?? '',
        prompt: json['prompt'] as String? ?? '',
        options: (json['options'] as List<dynamic>? ?? [])
            .map((e) => e.toString())
            .toList(),
        correctAnswer: json['correct_answer'] as String? ?? '',
      );
}

class ProfileUser {
  ProfileUser({required this.id, required this.name, required this.email});

  final int id;
  final String name;
  final String email;

  factory ProfileUser.fromJson(Map<String, dynamic> json) => ProfileUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
      );

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) {
      return email.isNotEmpty ? email[0].toUpperCase() : '?';
    }
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

class ProfileStats {
  ProfileStats({
    required this.vocabularyCount,
    required this.mediaCount,
    this.averageScorePercent,
  });

  final int vocabularyCount;
  final int mediaCount;
  final double? averageScorePercent;

  factory ProfileStats.fromJson(Map<String, dynamic> json) => ProfileStats(
        vocabularyCount: json['vocabulary_count'] as int? ?? 0,
        mediaCount: json['media_count'] as int? ?? 0,
        averageScorePercent: (json['average_score_percent'] as num?)?.toDouble(),
      );

  String get averageScoreLabel {
    if (averageScorePercent == null) return '—';
    return '${averageScorePercent!.round()}%';
  }
}

class ProfileHistoryItem {
  ProfileHistoryItem({
    required this.id,
    required this.kind,
    required this.title,
    required this.type,
    required this.score,
    required this.total,
    this.completedAt,
  });

  final String id;
  final String kind;
  final String title;
  final String type;
  final int score;
  final int total;
  final DateTime? completedAt;

  String get scoreLabel => '$score/$total';

  bool get isListening => kind == 'listening';

  factory ProfileHistoryItem.fromJson(Map<String, dynamic> json) =>
      ProfileHistoryItem(
        id: json['id'] as String? ?? '',
        kind: json['kind'] as String? ?? '',
        title: json['title'] as String? ?? '',
        type: json['type'] as String? ?? '',
        score: json['score'] as int? ?? 0,
        total: json['total'] as int? ?? 0,
        completedAt: json['completed_at'] != null
            ? DateTime.tryParse(json['completed_at'].toString())
            : null,
      );
}

class NotificationSettings {
  NotificationSettings({
    required this.vocabQuizPushEnabled,
    required this.globalVocabQuizPushEnabled,
    this.reminderSchedule,
  });

  final bool vocabQuizPushEnabled;
  final bool globalVocabQuizPushEnabled;
  final Map<String, dynamic>? reminderSchedule;

  bool get isActive => globalVocabQuizPushEnabled && vocabQuizPushEnabled;

  factory NotificationSettings.fromJson(Map<String, dynamic> json) =>
      NotificationSettings(
        vocabQuizPushEnabled: json['vocab_quiz_push_enabled'] as bool? ?? true,
        globalVocabQuizPushEnabled:
            json['global_vocab_quiz_push_enabled'] as bool? ?? true,
        reminderSchedule: json['reminder_schedule'] as Map<String, dynamic>?,
      );
}

class UserProfile {
  UserProfile({
    required this.user,
    required this.stats,
    required this.history,
  });

  final ProfileUser user;
  final ProfileStats stats;
  final List<ProfileHistoryItem> history;

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
        user: ProfileUser.fromJson(json['user'] as Map<String, dynamic>),
        stats: ProfileStats.fromJson(json['stats'] as Map<String, dynamic>),
        history: (json['history'] as List<dynamic>? ?? [])
            .map((e) => ProfileHistoryItem.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class AttemptResult {
  AttemptResult({
    required this.score,
    required this.total,
    required this.percentage,
    required this.passed,
    required this.results,
  });

  final int score;
  final int total;
  final double percentage;
  final bool passed;
  final List<Map<String, dynamic>> results;

  factory AttemptResult.fromJson(Map<String, dynamic> json) => AttemptResult(
        score: json['score'] as int? ?? 0,
        total: json['total'] as int? ?? 0,
        percentage: (json['percentage'] as num?)?.toDouble() ?? 0,
        passed: json['passed'] as bool? ?? false,
        results: (json['results'] as List<dynamic>? ?? [])
            .cast<Map<String, dynamic>>(),
      );
}
