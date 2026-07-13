import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class ListeningQuizScreen extends ConsumerStatefulWidget {
  const ListeningQuizScreen({
    super.key,
    required this.assessmentId,
    this.title,
  });

  final int assessmentId;
  final String? title;

  @override
  ConsumerState<ListeningQuizScreen> createState() => _ListeningQuizScreenState();
}

class _ListeningQuizScreenState extends ConsumerState<ListeningQuizScreen> {
  List<ListeningQuestion> _questions = [];
  final Map<int, String> _answers = {};
  bool _loading = true;
  AttemptResult? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final questions =
          await ref.read(flcApiProvider).getAssessmentQuestions(widget.assessmentId);
      setState(() => _questions = questions);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    final payload = _answers.entries
        .map((e) => {'question_id': e.key, 'answer': e.value})
        .toList();
    if (payload.length < _questions.length) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Answer all questions before submitting.')),
      );
      return;
    }
    setState(() => _loading = true);
    try {
      final result = await ref.read(flcApiProvider).submitListeningAttempt(
            assessmentId: widget.assessmentId,
            answers: payload,
          );
      setState(() => _result = result);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _questions.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.title ?? 'Listening quiz')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_result != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Results')),
        body: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${_result!.score}/${_result!.total} (${_result!.percentage.round()}%)',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              Text(
                _result!.passed ? 'Passed' : 'Not passed',
                style: TextStyle(
                  color: _result!.passed ? Colors.green : Colors.orange,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: ListView(
                  children: _result!.results.map((r) {
                    final ok = r['correct'] == true;
                    return Card(
                      child: ListTile(
                        title: Text(r['answer']?.toString() ?? ''),
                        subtitle: Text(r['explanation']?.toString() ?? ''),
                        trailing: Icon(
                          ok ? Icons.check : Icons.close,
                          color: ok ? Colors.green : Colors.red,
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(widget.title ?? 'Listening quiz')),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _questions.length + 1,
        itemBuilder: (context, i) {
          if (i == _questions.length) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: FilledButton(
                onPressed: _loading ? null : _submit,
                child: const Text('Submit'),
              ),
            );
          }
          final q = _questions[i];
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Question ${i + 1}', style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Text(q.prompt),
                  const SizedBox(height: 8),
                  if (q.options != null && q.options!.isNotEmpty)
                    RadioGroup<String>(
                      groupValue: _answers[q.id],
                      onChanged: (v) {
                        if (v == null) return;
                        setState(() => _answers[q.id] = v);
                      },
                      child: Column(
                        children: [
                          for (final opt in q.options!)
                            RadioListTile<String>(
                              title: Text(opt),
                              value: opt,
                            ),
                        ],
                      ),
                    )
                  else
                    TextField(
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        hintText: 'Your answer...',
                      ),
                      maxLines: 3,
                      onChanged: (v) => _answers[q.id] = v,
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
