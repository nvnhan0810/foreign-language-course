import 'dart:async';

import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/dictionary_card.dart';
import 'package:flc_mobile/widgets/game_topbar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

class ScrambleScreen extends ConsumerStatefulWidget {
  const ScrambleScreen({super.key, this.autostart = false});

  final bool autostart;

  @override
  ConsumerState<ScrambleScreen> createState() => _ScrambleScreenState();
}

class _ScrambleScreenState extends ConsumerState<ScrambleScreen> {
  static const _helpDelay = Duration(seconds: 15);

  ScramblePuzzle? _puzzle;
  ScrambleHint? _hint;
  ScrambleAttemptResult? _result;
  bool _loading = false;
  String? _error;
  bool _autoStarted = false;
  bool _submitting = false;
  bool _hintLoading = false;
  bool _helpUnlocked = false;

  final _answerController = TextEditingController();
  DateTime? _startedAt;
  int? _elapsedSeconds;
  Timer? _timer;
  Timer? _helpTimer;

  @override
  void initState() {
    super.initState();
    if (widget.autostart) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _autoStart());
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _helpTimer?.cancel();
    _answerController.dispose();
    super.dispose();
  }

  Future<void> _autoStart() async {
    if (_autoStarted) return;
    _autoStarted = true;
    await _next();
  }

  String _formatTime(int seconds) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  void _startTimers() {
    _timer?.cancel();
    _helpTimer?.cancel();
    _startedAt = DateTime.now();
    _elapsedSeconds = 0;
    _helpUnlocked = false;

    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted || _startedAt == null || _result != null) return;
      setState(() {
        _elapsedSeconds = DateTime.now().difference(_startedAt!).inSeconds;
      });
    });

    _helpTimer = Timer(_helpDelay, () {
      if (!mounted || _result != null || _hint != null) return;
      setState(() => _helpUnlocked = true);
    });
  }

  void _stopTimers({bool freezeElapsed = true}) {
    _timer?.cancel();
    _helpTimer?.cancel();
    if (freezeElapsed && _startedAt != null) {
      _elapsedSeconds = DateTime.now().difference(_startedAt!).inSeconds;
    }
  }

  Future<void> _next() async {
    setState(() {
      _loading = true;
      _error = null;
      _puzzle = null;
      _hint = null;
      _result = null;
      _helpUnlocked = false;
      _answerController.clear();
    });
    _stopTimers(freezeElapsed: false);
    _elapsedSeconds = null;
    _startedAt = null;

    try {
      final puzzle = await ref.read(flcApiProvider).nextScramblePuzzle();
      if (!mounted) return;
      setState(() => _puzzle = puzzle);
      _startTimers();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _requestHint() async {
    final puzzle = _puzzle;
    if (puzzle == null || _hint != null || _result != null || !_helpUnlocked) return;

    setState(() => _hintLoading = true);
    try {
      final hint = await ref.read(flcApiProvider).getScrambleHint(puzzle.vocabularyId);
      if (!mounted) return;
      setState(() => _hint = hint);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _hintLoading = false);
    }
  }

  Future<void> _submit() async {
    final puzzle = _puzzle;
    final answer = _answerController.text.trim();
    if (puzzle == null || answer.isEmpty || _result != null || _submitting) return;

    setState(() => _submitting = true);
    try {
      final result = await ref.read(flcApiProvider).submitScrambleAttempt(
            vocabularyId: puzzle.vocabularyId,
            answer: answer,
          );
      if (!mounted) return;
      _stopTimers();
      setState(() => _result = result);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _confirmLeave() async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Leave this round?'),
        content: const Text("Your current round won't be saved."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Stay')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Leave')),
        ],
      ),
    );
    if (leave == true && mounted) {
      if (context.canPop()) {
        context.pop();
      } else {
        context.go('/home/puzzle');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final puzzle = _puzzle;
    final result = _result;
    final answered = result != null;
    final displaySeconds = _elapsedSeconds ?? 0;

    return Scaffold(
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            GameTopbar(
              title: answered ? _formatTime(displaySeconds) : (puzzle == null ? 'Scramble' : null),
              onClose: _confirmLeave,
              actions: [
                if (puzzle != null)
                  Center(
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: scheme.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        '${puzzle.wordLength} LTR',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                      ),
                    ),
                  ),
              ],
              bottomWidget: puzzle != null && !answered
                  ? Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Center(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                          decoration: BoxDecoration(
                            color: scheme.primaryContainer.withValues(alpha: 0.55),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            'TIME  ${_formatTime(displaySeconds)}',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.6,
                              color: scheme.onPrimaryContainer,
                            ),
                          ),
                        ),
                      ),
                    )
                  : null,
            ),
            Expanded(
              child: _loading && puzzle == null
                  ? const Center(child: CircularProgressIndicator())
                  : puzzle == null
                      ? _IdleBody(
                          error: _error,
                          onPlay: _next,
                        )
                      : SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                        'Unscramble',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                      const SizedBox(height: 16),
                      Wrap(
                        alignment: WrapAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: puzzle.scrambled
                            .toUpperCase()
                            .split('')
                            .map(
                              (letter) => Container(
                                width: 44,
                                height: 52,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: scheme.primaryContainer.withValues(alpha: 0.45),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: scheme.outlineVariant),
                                ),
                                child: Text(
                                  letter,
                                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                        fontWeight: FontWeight.w800,
                                      ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                      const SizedBox(height: 24),
                      if (_hint != null && !answered) ...[
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: scheme.secondaryContainer.withValues(alpha: 0.45),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Hint',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: scheme.onSecondaryContainer,
                                ),
                              ),
                              if (_hint!.partOfSpeech != null &&
                                  _hint!.partOfSpeech!.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  _hint!.partOfSpeech!,
                                  style: TextStyle(
                                    fontStyle: FontStyle.italic,
                                    color: scheme.onSecondaryContainer.withValues(alpha: 0.8),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 6),
                              Text(_hint!.definition),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (!answered) ...[
                        TextField(
                          controller: _answerController,
                          autofocus: true,
                          textInputAction: TextInputAction.done,
                          autocorrect: false,
                          enableSuggestions: false,
                          maxLength: 40,
                          decoration: const InputDecoration(
                            labelText: 'Your guess',
                            counterText: '',
                            border: OutlineInputBorder(),
                          ),
                          onSubmitted: (_) => _submit(),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: FilledButton(
                                onPressed: _submitting ? null : _submit,
                                child: _submitting
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : const Text('Submit'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: OutlinedButton(
                                onPressed: (!_helpUnlocked ||
                                        _hint != null ||
                                        _hintLoading ||
                                        _submitting)
                                    ? null
                                    : _requestHint,
                                child: _hintLoading
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : Text(
                                        _hint != null
                                            ? 'Help used'
                                            : _helpUnlocked
                                                ? 'Help'
                                                : 'Help (${(_helpDelay.inSeconds - displaySeconds).clamp(0, _helpDelay.inSeconds)}s)',
                                      ),
                              ),
                            ),
                          ],
                        ),
                      ] else ...[
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: result.correct ? Colors.green.shade50 : Colors.red.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: result.correct ? Colors.green.shade200 : Colors.red.shade200,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    result.correct ? Icons.check_circle : Icons.cancel,
                                    color: result.correct ? Colors.green : Colors.red,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      result.correct ? 'Nice!' : 'Not quite',
                                      style: TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 18,
                                        color: result.correct
                                            ? Colors.green.shade800
                                            : Colors.red.shade800,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    _formatTime(displaySeconds),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: result.correct
                                          ? Colors.green.shade800
                                          : Colors.red.shade800,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                result.correct
                                    ? 'Correct: ${result.correctWord}'
                                    : 'Answer: ${result.correctWord}',
                              ),
                            ],
                          ),
                        ),
                        if (result.entry != null) ...[
                          const SizedBox(height: 16),
                          DictionaryCard.fromVocabulary(result.entry!),
                        ],
                        const SizedBox(height: 20),
                        SizedBox(
                          height: 50,
                          child: FilledButton(
                            onPressed: _next,
                            child: const Text(
                              'Next round →',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
            ),
          ],
        ),
      ),
    );
  }
}

class _IdleBody extends StatelessWidget {
  const _IdleBody({required this.onPlay, this.error});

  final VoidCallback onPlay;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Wrap(
              spacing: 6,
              children: 'SCRAM'
                  .split('')
                  .map(
                    (c) => Container(
                      width: 40,
                      height: 48,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: scheme.primaryContainer.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        c,
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20),
                      ),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 20),
            Text(
              'Scramble',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              'Unscramble letters. Beat the clock.',
              textAlign: TextAlign.center,
              style: TextStyle(color: scheme.onSurfaceVariant),
            ),
            if (error != null) ...[
              const SizedBox(height: 16),
              Text(
                error!,
                textAlign: TextAlign.center,
                style: TextStyle(color: scheme.error),
              ),
            ],
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: onPlay,
              icon: const Icon(Icons.play_arrow),
              label: const Text('Play', style: TextStyle(fontSize: 16)),
            ),
          ],
        ),
      ),
    );
  }
}
