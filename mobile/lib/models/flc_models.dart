class Meaning {
  Meaning({
    this.partOfSpeech,
    required this.definition,
    this.example,
  });

  final String? partOfSpeech;
  final String definition;
  final String? example;

  factory Meaning.fromJson(Map<String, dynamic> json) => Meaning(
        partOfSpeech: json['part_of_speech'] as String?,
        definition: json['definition'] as String? ?? '',
        example: json['example'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'part_of_speech': partOfSpeech,
        'definition': definition,
        if (example != null) 'example': example,
      };
}

class DictionaryResult {
  DictionaryResult({
    required this.word,
    this.phonetic,
    this.audioUrl,
    required this.meanings,
    this.source,
  });

  final String word;
  final String? phonetic;
  final String? audioUrl;
  final List<Meaning> meanings;
  final String? source;

  factory DictionaryResult.fromJson(Map<String, dynamic> json) =>
      DictionaryResult(
        word: json['word'] as String,
        phonetic: json['phonetic'] as String?,
        audioUrl: json['audio_url'] as String?,
        meanings: (json['meanings'] as List<dynamic>? ?? [])
            .map((e) => Meaning.fromJson(e as Map<String, dynamic>))
            .toList(),
        source: json['source'] as String?,
      );
}

class Vocabulary {
  Vocabulary({
    required this.id,
    required this.word,
    this.phonetic,
    required this.meanings,
    this.timesQuizzed = 0,
  });

  final int id;
  final String word;
  final String? phonetic;
  final List<Meaning> meanings;
  final int timesQuizzed;

  factory Vocabulary.fromJson(Map<String, dynamic> json) => Vocabulary(
        id: json['id'] as int,
        word: json['word'] as String,
        phonetic: json['phonetic'] as String?,
        meanings: (json['meanings'] as List<dynamic>? ?? [])
            .map((e) => Meaning.fromJson(e as Map<String, dynamic>))
            .toList(),
        timesQuizzed: json['times_quizzed'] as int? ?? 0,
      );
}

class MediaItem {
  MediaItem({
    required this.id,
    required this.title,
    required this.url,
    required this.type,
    this.frequency = 'weekly',
    this.analysisStatus,
    this.sourceId,
    this.language,
  });

  final int id;
  final String title;
  final String url;
  final String type;
  final String frequency;
  final String? analysisStatus;
  final String? sourceId;
  final String? language;

  bool get isYoutube => type == 'youtube';
  bool get isReady => analysisStatus == 'ready';

  factory MediaItem.fromJson(Map<String, dynamic> json) => MediaItem(
        id: json['id'] as int,
        title: json['title'] as String,
        url: json['url'] as String? ?? '',
        type: json['type'] as String,
        frequency: json['frequency'] as String? ?? 'weekly',
        analysisStatus: json['analysis_status'] as String?,
        sourceId: json['source_id'] as String?,
        language: json['language'] as String?,
      );
}

class ListeningAssessmentSummary {
  ListeningAssessmentSummary({
    required this.id,
    required this.type,
    required this.title,
    required this.questionCount,
    required this.status,
    this.timeLimitMinutes,
  });

  final int id;
  final String type;
  final String title;
  final int questionCount;
  final String status;
  final int? timeLimitMinutes;

  bool get isReady => status == 'ready';

  factory ListeningAssessmentSummary.fromJson(Map<String, dynamic> json) =>
      ListeningAssessmentSummary(
        id: json['id'] as int,
        type: json['type'] as String,
        title: json['title'] as String,
        questionCount: json['question_count'] as int? ?? 0,
        status: json['status'] as String? ?? 'generating',
        timeLimitMinutes: json['time_limit_minutes'] as int?,
      );
}

class ListeningQuestion {
  ListeningQuestion({
    required this.id,
    required this.order,
    required this.questionType,
    required this.prompt,
    this.options,
  });

  final int id;
  final int order;
  final String questionType;
  final String prompt;
  final List<String>? options;

  factory ListeningQuestion.fromJson(Map<String, dynamic> json) =>
      ListeningQuestion(
        id: json['id'] as int,
        order: json['order'] as int? ?? 0,
        questionType: json['question_type'] as String,
        prompt: json['prompt'] as String,
        options: (json['options'] as List<dynamic>?)
            ?.map((e) => e.toString())
            .toList(),
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
        questionType: json['question_type'] as String,
        prompt: json['prompt'] as String,
        options: (json['options'] as List<dynamic>? ?? [])
            .map((e) => e.toString())
            .toList(),
        correctAnswer: json['correct_answer'] as String,
      );
}

class ProfileUser {
  ProfileUser({
    required this.id,
    required this.name,
    required this.email,
  });

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
        id: json['id'] as String,
        kind: json['kind'] as String,
        title: json['title'] as String,
        type: json['type'] as String,
        score: json['score'] as int,
        total: json['total'] as int,
        completedAt: json['completed_at'] != null
            ? DateTime.tryParse(json['completed_at'] as String)
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
        reminderSchedule:
            json['reminder_schedule'] as Map<String, dynamic>?,
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
        score: json['score'] as int,
        total: json['total'] as int,
        percentage: (json['percentage'] as num).toDouble(),
        passed: json['passed'] as bool? ?? false,
        results: (json['results'] as List<dynamic>? ?? [])
            .cast<Map<String, dynamic>>(),
      );
}
