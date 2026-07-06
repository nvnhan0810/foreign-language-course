import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:just_audio/just_audio.dart' as ja;
import 'package:url_launcher/url_launcher.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

class MediaDetailScreen extends ConsumerStatefulWidget {
  const MediaDetailScreen({super.key, required this.mediaId, this.initial});

  final int mediaId;
  final MediaItem? initial;

  @override
  ConsumerState<MediaDetailScreen> createState() => _MediaDetailScreenState();
}

class _MediaDetailScreenState extends ConsumerState<MediaDetailScreen> {
  MediaItem? _media;
  List<ListeningSessionOption> _options = [];
  YoutubePlayerController? _ytController;
  ja.AudioPlayer? _audioPlayer;
  bool _loading = true;
  String? _error;
  String? _startingType;
  bool _editingTranscript = false;
  bool _savingTranscript = false;
  late final TextEditingController _transcriptController;

  @override
  void initState() {
    super.initState();
    _media = widget.initial;
    _transcriptController = TextEditingController(text: widget.initial?.transcript ?? '');
    _load();
  }

  @override
  void dispose() {
    _transcriptController.dispose();
    _ytController?.dispose();
    _audioPlayer?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = ref.read(flcApiProvider);
      final media = await api.getMedia(widget.mediaId);
      final options = await api.listListeningSessionOptions(widget.mediaId);
      setState(() {
        _media = media;
        _options = options;
      });
      _transcriptController.text = media.transcript ?? '';

      if (media.isYoutube && media.sourceId != null) {
        _ytController?.dispose();
        _ytController = YoutubePlayerController(
          initialVideoId: media.sourceId!,
          flags: const YoutubePlayerFlags(autoPlay: false, enableCaption: true),
        );
      }
      if (!media.isYoutube) {
        await _initAudio(media.id);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _initAudio(int mediaId) async {
    final token = await ref.read(tokenStorageProvider).getToken();
    final url = ref.read(flcApiProvider).audioUrl(mediaId);
    _audioPlayer ??= ja.AudioPlayer();
    await _audioPlayer!.setUrl(url, headers: {
      if (token != null) 'Authorization': 'Bearer $token',
    });
  }

  String? _youtubeWatchUrl(MediaItem m) {
    if (m.url.isNotEmpty) return m.url;
    final id = m.sourceId;
    if (id != null && id.isNotEmpty) {
      return 'https://www.youtube.com/watch?v=$id';
    }
    return null;
  }

  Future<void> _openInYoutube(MediaItem m) async {
    final url = _youtubeWatchUrl(m);
    if (url == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Không tìm thấy link YouTube cho bài này.')),
      );
      return;
    }

    final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    if (!ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Không mở được YouTube. Thử lại sau.')),
      );
    }
  }

  Future<void> _startSession(ListeningSessionOption option) async {
    if (!option.available) return;
    setState(() => _startingType = option.type);
    try {
      final session = await ref.read(flcApiProvider).startListeningSession(
            mediaId: widget.mediaId,
            type: option.type,
          );
      final assessmentId = session['assessment_id'] as int?;
      final title = session['title'] as String? ?? option.title;
      if (assessmentId == null) {
        throw ApiException('Không nhận được phiên làm bài.');
      }
      if (!mounted) return;
      context.push('/listening/$assessmentId', extra: title);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _startingType = null);
    }
  }

  void _startEditTranscript() {
    _transcriptController.text = _media?.transcript ?? '';
    setState(() => _editingTranscript = true);
  }

  void _cancelEditTranscript() {
    _transcriptController.text = _media?.transcript ?? '';
    setState(() => _editingTranscript = false);
  }

  Future<void> _saveTranscript() async {
    setState(() => _savingTranscript = true);
    try {
      final text = _transcriptController.text.trim();
      final updated = await ref.read(flcApiProvider).updateMediaTranscript(
            mediaId: widget.mediaId,
            transcript: text.isEmpty ? null : text,
          );
      setState(() {
        _media = updated;
        _editingTranscript = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã lưu transcript.')),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _savingTranscript = false);
    }
  }

  Widget _buildTranscriptSection(MediaItem m) {
    final hasTranscript = m.transcript != null && m.transcript!.trim().isNotEmpty;

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: ExpansionTile(
        initiallyExpanded: _editingTranscript,
        title: const Text('Transcript', style: TextStyle(fontWeight: FontWeight.bold)),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: _editingTranscript
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: _transcriptController,
                        maxLines: 10,
                        decoration: const InputDecoration(
                          border: OutlineInputBorder(),
                          hintText: 'Nhập transcript của video hoặc audio...',
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          FilledButton(
                            onPressed: _savingTranscript ? null : _saveTranscript,
                            child: _savingTranscript
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : const Text('Lưu'),
                          ),
                          const SizedBox(width: 8),
                          TextButton(
                            onPressed: _savingTranscript ? null : _cancelEditTranscript,
                            child: const Text('Huỷ'),
                          ),
                        ],
                      ),
                    ],
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        hasTranscript ? m.transcript! : 'Chưa có transcript.',
                        style: TextStyle(
                          color: hasTranscript ? null : Colors.grey.shade600,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: OutlinedButton(
                          onPressed: _startEditTranscript,
                          child: Text(hasTranscript ? 'Sửa transcript' : 'Thêm transcript'),
                        ),
                      ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final m = _media;
    return Scaffold(
      appBar: AppBar(title: Text(m?.title ?? 'Media')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      TextButton(onPressed: _load, child: const Text('Thử lại')),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (m != null) ...[
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: Theme.of(context)
                                .colorScheme
                                .secondaryContainer
                                .withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            m.difficultyLabel,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Theme.of(context).colorScheme.onSecondaryContainer,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    if (m != null && m.isYoutube && _ytController != null) ...[
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: YoutubePlayer(controller: _ytController!),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () => _openInYoutube(m),
                          icon: const Icon(Icons.open_in_new),
                          label: const Text('Mở trên YouTube'),
                        ),
                      ),
                    ],
                    if (m != null && !m.isYoutube && _audioPlayer != null) ...[
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Theme.of(context)
                              .colorScheme
                              .primaryContainer
                              .withValues(alpha: 0.3),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: StreamBuilder<ja.PlayerState>(
                          stream: _audioPlayer!.playerStateStream,
                          builder: (context, snap) {
                            final playing = snap.data?.playing ?? false;
                            return Row(
                              children: [
                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).colorScheme.primary,
                                    shape: BoxShape.circle,
                                  ),
                                  child: IconButton(
                                    icon: Icon(
                                      playing ? Icons.pause : Icons.play_arrow,
                                      color: Colors.white,
                                    ),
                                    onPressed: () => playing
                                        ? _audioPlayer!.pause()
                                        : _audioPlayer!.play(),
                                  ),
                                ),
                                const SizedBox(width: 16),
                                const Text(
                                  'MP3 / Audio',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                              ],
                            );
                          },
                        ),
                      ),
                    ],
                    if (m != null) _buildTranscriptSection(m),
                    const SizedBox(height: 8),
                    const Text(
                      'Bài quiz / test / thi',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                    ),
                    const SizedBox(height: 8),
                    if (_options.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(
                          child: Text(
                            'Chưa có bài. Đợi AI tạo ngân hàng câu hỏi.',
                            style: TextStyle(color: Colors.grey),
                          ),
                        ),
                      )
                    else
                      ..._options.map((o) {
                        final starting = _startingType == o.type;
                        return Card(
                          margin: const EdgeInsets.only(bottom: 12),
                          child: ListTile(
                            contentPadding:
                                const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            leading: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: o.available
                                    ? Theme.of(context)
                                        .colorScheme
                                        .primaryContainer
                                        .withValues(alpha: 0.5)
                                    : Colors.grey.shade200,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                o.available ? Icons.quiz : Icons.hourglass_empty,
                                color: o.available
                                    ? Theme.of(context).colorScheme.primary
                                    : Colors.grey,
                              ),
                            ),
                            title: Text(
                              '${o.type.toUpperCase()}: ${o.title}',
                              style: const TextStyle(fontWeight: FontWeight.bold),
                            ),
                            subtitle: Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text(
                                '${o.questionCount} câu'
                                '${o.timeLimitMinutes != null ? ' · ${o.timeLimitMinutes} phút' : ''}'
                                '${!o.available ? ' · Chưa sẵn sàng' : ''}',
                                style: TextStyle(color: Colors.grey.shade600),
                              ),
                            ),
                            trailing: starting
                                ? const SizedBox(
                                    width: 24,
                                    height: 24,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  )
                                : const Icon(Icons.chevron_right),
                            onTap: o.available && !starting ? () => _startSession(o) : null,
                          ),
                        );
                      }),
                  ],
                ),
    );
  }
}
