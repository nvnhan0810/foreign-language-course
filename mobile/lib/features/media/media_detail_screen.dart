import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:just_audio/just_audio.dart' as ja;
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
  List<ListeningAssessmentSummary> _assessments = [];
  YoutubePlayerController? _ytController;
  ja.AudioPlayer? _audioPlayer;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _media = widget.initial;
    _load();
  }

  @override
  void dispose() {
    _ytController?.dispose();
    _audioPlayer?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final assessments = await ref.read(flcApiProvider).listAssessments(widget.mediaId);
      setState(() => _assessments = assessments);

      final m = _media;
      if (m != null && m.isYoutube && m.sourceId != null) {
        _ytController?.dispose();
        _ytController = YoutubePlayerController(
          initialVideoId: m.sourceId!,
          flags: const YoutubePlayerFlags(autoPlay: false, enableCaption: true),
        );
      }
      if (m != null && !m.isYoutube) {
        await _initAudio(m.id);
      }
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

  @override
  Widget build(BuildContext context) {
    final m = _media;
    return Scaffold(
      appBar: AppBar(title: Text(m?.title ?? 'Media')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (m != null && m.isYoutube && _ytController != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: YoutubePlayer(controller: _ytController!),
                  ),
                if (m != null && !m.isYoutube && _audioPlayer != null) ...[
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.3),
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
                                icon: Icon(playing ? Icons.pause : Icons.play_arrow, color: Colors.white),
                                onPressed: () => playing
                                    ? _audioPlayer!.pause()
                                    : _audioPlayer!.play(),
                              ),
                            ),
                            const SizedBox(width: 16),
                            const Text('MP3 / Audio', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                          ],
                        );
                      },
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                const Text('Bài quiz / test / thi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                const SizedBox(height: 8),
                if (_assessments.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: Text('Chưa có bài. Tạo từ trang admin.', style: TextStyle(color: Colors.grey))),
                  )
                else
                  ..._assessments.map((a) => Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          leading: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: a.isReady 
                                  ? Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.5)
                                  : Colors.grey.shade200,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              a.isReady ? Icons.quiz : Icons.hourglass_empty,
                              color: a.isReady ? Theme.of(context).colorScheme.primary : Colors.grey,
                            ),
                          ),
                          title: Text('${a.type.toUpperCase()}: ${a.title}', style: const TextStyle(fontWeight: FontWeight.bold)),
                          subtitle: Padding(
                            padding: const EdgeInsets.only(top: 4.0),
                            child: Text(
                              '${a.questionCount} câu · ${a.status}'
                              '${a.timeLimitMinutes != null ? ' · ${a.timeLimitMinutes} phút' : ''}',
                              style: TextStyle(color: Colors.grey.shade600),
                            ),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: a.isReady
                              ? () => context.push(
                                    '/listening/${a.id}',
                                    extra: {'media': m, 'assessment': a},
                                  )
                              : null,
                        ),
                      )),
              ],
            ),
    );
  }
}
