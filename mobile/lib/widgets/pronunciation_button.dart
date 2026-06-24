import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:just_audio/just_audio.dart';

final _pronunciationPlayer = AudioPlayer();

class PronunciationButton extends ConsumerStatefulWidget {
  const PronunciationButton({
    super.key,
    this.audioUrl,
    required this.word,
    this.iconSize = 22,
  });

  final String? audioUrl;
  final String word;
  final double iconSize;

  @override
  ConsumerState<PronunciationButton> createState() => _PronunciationButtonState();
}

class _PronunciationButtonState extends ConsumerState<PronunciationButton> {
  bool _loading = false;

  Future<void> _play() async {
    if (_loading) return;

    setState(() => _loading = true);
    try {
      var audioUrl = widget.audioUrl;

      if (audioUrl == null || audioUrl.isEmpty) {
        final result = await ref.read(flcApiProvider).lookup(widget.word);
        audioUrl = result.audioUrl;
      }

      if (audioUrl != null && audioUrl.isNotEmpty) {
        await _pronunciationPlayer.stop();
        await _pronunciationPlayer.setUrl(audioUrl);
        await _pronunciationPlayer.play();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Không phát được âm thanh')),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: _loading ? null : _play,
      tooltip: 'Nghe phát âm',
      icon: _loading
          ? SizedBox(
              width: widget.iconSize,
              height: widget.iconSize,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Icon(Icons.volume_up_rounded, size: widget.iconSize),
    );
  }
}
