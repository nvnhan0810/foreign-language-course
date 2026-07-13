import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class VocabQuizScreen extends ConsumerStatefulWidget {
  const VocabQuizScreen({super.key, this.autostart = false});

  final bool autostart;

  @override
  ConsumerState<VocabQuizScreen> createState() => _VocabQuizScreenState();
}

class _VocabQuizScreenState extends ConsumerState<VocabQuizScreen> {
  QuizQuestion? _question;
  bool _loading = false;
  String? _feedback;
  String? _selectedChoice;
  bool? _wasCorrect;
  bool _autoStarted = false;

  @override
  void initState() {
    super.initState();
    if (widget.autostart) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _autoStart());
    }
  }

  Future<void> _autoStart() async {
    if (_autoStarted) return;
    _autoStarted = true;
    await _next();
  }

  Future<void> _next() async {
    setState(() {
      _loading = true;
      _feedback = null;
      _selectedChoice = null;
      _wasCorrect = null;
    });
    try {
      final q = await ref.read(flcApiProvider).nextVocabQuiz();
      setState(() => _question = q);
    } on ApiException catch (e) {
      setState(() => _feedback = e.message);
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _answer(String choice) async {
    final q = _question;
    if (q == null || _wasCorrect != null) return;
    final correct = choice.trim().toLowerCase() == q.correctAnswer.trim().toLowerCase();
    setState(() {
      _selectedChoice = choice;
      _wasCorrect = correct;
      _feedback = correct ? 'Đúng!' : 'Sai. Đáp án: ${q.correctAnswer}';
    });
    await ref.read(flcApiProvider).submitVocabQuizAttempt(
          vocabularyId: q.vocabularyId,
          questionType: q.questionType,
          correct: correct,
        );
  }

  bool _sameOption(String a, String b) =>
      a.trim().toLowerCase() == b.trim().toLowerCase();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Quiz từ vựng (ôn từ đã lưu)',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 16),
          if (_question == null && !_loading)
            Center(
              child: Padding(
                padding: const EdgeInsets.only(top: 40),
                child: FilledButton.icon(
                  onPressed: _next, 
                  icon: const Icon(Icons.play_arrow),
                  label: const Text('Bắt đầu Quiz', style: TextStyle(fontSize: 16)),
                ),
              ),
            ),
          if (_loading) const Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator())),
          if (_question != null) ...[
            Card(
              margin: const EdgeInsets.only(bottom: 24),
              color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.3),
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  children: [
                    Text(
                      _question!.questionType == 'word_to_definition' ? 'Chọn nghĩa đúng' : 'Chọn từ đúng',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      _question!.prompt,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
            ..._question!.options.map(
              (opt) {
                final revealed = _wasCorrect != null;
                final isCorrectOpt = _sameOption(opt, _question!.correctAnswer);
                final isSelectedOpt =
                    _selectedChoice != null && _sameOption(opt, _selectedChoice!);

                Color? bgColor;
                Color? textColor;
                Color? borderColor;

                if (revealed) {
                  if (isCorrectOpt) {
                    bgColor = Colors.green.shade100;
                    textColor = Colors.green.shade900;
                    borderColor = Colors.green.shade700;
                  } else if (isSelectedOpt) {
                    bgColor = Colors.red.shade100;
                    textColor = Colors.red.shade900;
                    borderColor = Colors.red.shade700;
                  }
                }

                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.all(16),
                      backgroundColor: bgColor,
                      foregroundColor: textColor,
                      // Keep result colors readable: disabled buttons otherwise
                      // fade the label into the tinted background.
                      disabledBackgroundColor: bgColor,
                      disabledForegroundColor: textColor,
                      side: borderColor != null ? BorderSide(color: borderColor) : null,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: revealed ? null : () => _answer(opt),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        opt,
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: revealed && (isCorrectOpt || isSelectedOpt)
                              ? FontWeight.w600
                              : FontWeight.w400,
                          color: textColor,
                        ),
                      ),
                    ),
                  ),
                );
              }
            ),
            if (_feedback != null)
              Container(
                margin: const EdgeInsets.only(top: 16, bottom: 24),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: _wasCorrect == true ? Colors.green.shade50 : Colors.red.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: _wasCorrect == true ? Colors.green.shade200 : Colors.red.shade200,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      _wasCorrect == true ? Icons.check_circle : Icons.cancel,
                      color: _wasCorrect == true ? Colors.green : Colors.red,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        _feedback!,
                        style: TextStyle(
                          color: _wasCorrect == true ? Colors.green.shade800 : Colors.red.shade800,
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (_wasCorrect != null)
              SizedBox(
                height: 50,
                child: FilledButton(
                  onPressed: _next,
                  child: const Text('Câu tiếp theo', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ),
              ),
          ],
        ],
      ),
    );
  }
}
