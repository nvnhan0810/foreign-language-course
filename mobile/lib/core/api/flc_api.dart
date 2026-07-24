import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/models/flc_models.dart';

class FlcApi {
  FlcApi(this._client);

  final ApiClient _client;

  Future<DictionaryResult> lookup(String word) async {
    final json = await _client.get<Map<String, dynamic>>(
      '/dictionary/${Uri.encodeComponent(word.trim())}',
      parser: (d) => d as Map<String, dynamic>,
    );
    return DictionaryResult.fromJson(json);
  }

  Future<List<Vocabulary>> listVocabularies() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/vocabularies',
      parser: (d) => d as Map<String, dynamic>,
    );
    return (json['data'] as List<dynamic>)
        .map((e) => Vocabulary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Vocabulary> saveVocabulary({
    required String word,
    String? phonetic,
    List<Meaning>? meanings,
  }) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/vocabularies',
      data: {
        'word': word,
        if (phonetic != null) 'phonetic': phonetic,
        if (meanings != null) 'meanings': meanings.map((m) => m.toJson()).toList(),
      },
      parser: (d) => d as Map<String, dynamic>,
    );
    return Vocabulary.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> deleteVocabulary(int id) => _client.delete('/vocabularies/$id');

  Future<List<MediaItem>> listMedia() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/media-items',
      parser: (d) => d as Map<String, dynamic>,
    );
    return (json['data'] as List<dynamic>)
        .map((e) => MediaItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<YouTubePreview> previewYouTube(String url) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/listening/media/youtube-preview',
      data: {'url': url},
      parser: (d) => d as Map<String, dynamic>,
    );
    return YouTubePreview.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<MediaItem> createListeningMedia({
    required String title,
    required String url,
    String type = 'youtube',
    String frequency = 'weekly',
    String difficulty = 'intermediate',
    bool autoProcess = true,
  }) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/listening/media',
      data: {
        'title': title,
        'url': url,
        'type': type,
        'frequency': frequency,
        'difficulty': difficulty,
        'auto_process': autoProcess,
      },
      parser: (d) => d as Map<String, dynamic>,
    );
    return MediaItem.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<MediaItem> getMedia(int mediaId) async {
    final json = await _client.get<Map<String, dynamic>>(
      '/listening/media/$mediaId',
      parser: (d) => d as Map<String, dynamic>,
    );
    return MediaItem.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<MediaItem> updateMediaTranscript({
    required int mediaId,
    String? transcript,
  }) async {
    final json = await _client.put<Map<String, dynamic>>(
      '/listening/media/$mediaId/transcript',
      data: {'transcript': transcript},
      parser: (d) => d as Map<String, dynamic>,
    );
    return MediaItem.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<List<ListeningSessionOption>> listListeningSessionOptions(int mediaId) async {
    final json = await _client.get<Map<String, dynamic>>(
      '/listening/media/$mediaId/assessments',
      parser: (d) => d as Map<String, dynamic>,
    );
    return (json['data'] as List<dynamic>)
        .map((e) => ListeningSessionOption.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Map<String, dynamic>> startListeningSession({
    required int mediaId,
    required String type,
  }) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/listening/media/$mediaId/sessions',
      data: {'type': type},
      parser: (d) => d as Map<String, dynamic>,
    );
    return json['data'] as Map<String, dynamic>;
  }

  String audioUrl(int mediaId) => '$apiBaseUrl/listening/media/$mediaId/audio';

  Future<List<ListeningQuestion>> getAssessmentQuestions(int assessmentId) async {
    final json = await _client.get<Map<String, dynamic>>(
      '/listening/assessments/$assessmentId/questions',
      parser: (d) => d as Map<String, dynamic>,
    );
    final data = json['data'] as Map<String, dynamic>;
    return (data['questions'] as List<dynamic>)
        .map((e) => ListeningQuestion.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<AttemptResult> submitListeningAttempt({
    required int assessmentId,
    required List<Map<String, dynamic>> answers,
  }) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/listening/assessments/$assessmentId/attempts',
      data: {'answers': answers},
      parser: (d) => d as Map<String, dynamic>,
    );
    return AttemptResult.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<QuizQuestion> nextVocabQuiz() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/quiz/next',
      parser: (d) => d as Map<String, dynamic>,
    );
    return QuizQuestion.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> submitVocabQuizAttempt({
    required int vocabularyId,
    required String questionType,
    required bool correct,
  }) async {
    await _client.post(
      '/quiz/attempts',
      data: {
        'vocabulary_id': vocabularyId,
        'question_type': questionType,
        'correct': correct,
      },
    );
  }

  Future<ScramblePuzzle> nextScramblePuzzle() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/puzzle/scramble/next',
      parser: (d) => d as Map<String, dynamic>,
    );
    return ScramblePuzzle.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<ScrambleHint> getScrambleHint(int vocabularyId) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/puzzle/scramble/hint',
      data: {'vocabulary_id': vocabularyId},
      parser: (d) => d as Map<String, dynamic>,
    );
    return ScrambleHint.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<ScrambleAttemptResult> submitScrambleAttempt({
    required int vocabularyId,
    required String answer,
  }) async {
    final json = await _client.post<Map<String, dynamic>>(
      '/puzzle/scramble/attempts',
      data: {
        'vocabulary_id': vocabularyId,
        'answer': answer,
      },
      parser: (d) => d as Map<String, dynamic>,
    );
    return ScrambleAttemptResult.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> registerPushToken({
    required String token,
    required String platform,
  }) async {
    await _client.post(
      '/me/push-token',
      data: {'token': token, 'platform': platform},
    );
  }

  Future<void> deletePushToken(String token) async {
    await _client.delete(
      '/me/push-token',
      data: {'token': token},
    );
  }

  Future<UserProfile> getProfile() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/profile',
      parser: (d) => d as Map<String, dynamic>,
    );
    return UserProfile.fromJson(json);
  }

  Future<NotificationSettings> getNotificationSettings() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/me/notification-settings',
      parser: (d) => d as Map<String, dynamic>,
    );
    return NotificationSettings.fromJson(json);
  }

  Future<NotificationSettings> updateNotificationSettings({
    required bool vocabQuizPushEnabled,
  }) async {
    await _client.put(
      '/me/notification-settings',
      data: {'vocab_quiz_push_enabled': vocabQuizPushEnabled},
    );
    return getNotificationSettings();
  }

  Future<void> logout() => _client.post('/logout');

  Future<List<AgentApiToken>> listAgentTokens() async {
    final json = await _client.get<Map<String, dynamic>>(
      '/me/agent-tokens',
      parser: (d) => d as Map<String, dynamic>,
    );
    return (json['data'] as List<dynamic>? ?? [])
        .map((e) => AgentApiToken.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CreatedAgentApiToken> createAgentToken() async {
    final json = await _client.post<Map<String, dynamic>>(
      '/me/agent-tokens',
      data: const {},
      parser: (d) => d as Map<String, dynamic>,
    );
    return CreatedAgentApiToken(
      token: AgentApiToken.fromJson(json['data'] as Map<String, dynamic>),
      plainText: json['token'] as String? ?? '',
    );
  }

  Future<void> revokeAgentToken(int id) =>
      _client.delete('/me/agent-tokens/$id');
}
