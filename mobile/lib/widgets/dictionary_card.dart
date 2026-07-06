import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/pronunciation_button.dart';
import 'package:flutter/material.dart';

class DictionaryCard extends StatelessWidget {
  const DictionaryCard({super.key, required this.result});

  final DictionaryResult result;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 8,
                    children: [
                      Text(
                        result.word,
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      if (result.phonetic != null)
                        Text(
                          result.phonetic!,
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Colors.grey.shade600,
                                fontStyle: FontStyle.italic,
                              ),
                        ),
                    ],
                  ),
                ),
                PronunciationButton(
                  audioUrl: result.audioUrl,
                  word: result.word,
                ),
              ],
            ),
            const SizedBox(height: 12),
            ...result.meanings.take(6).map((m) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (m.partOfSpeech != null)
                        Container(
                          margin: const EdgeInsets.only(bottom: 4),
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            m.partOfSpeech!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.primary,
                              fontStyle: FontStyle.italic,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      Text(m.definition),
                      if (m.example != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            '"${m.example}"',
                            style: TextStyle(
                              color: Colors.grey.shade700,
                              fontSize: 13,
                            ),
                          ),
                        ),
                    ],
                  ),
                )),
          ],
        ),
      ),
    );
  }
}
