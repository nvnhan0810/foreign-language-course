import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/pronunciation_button.dart';
import 'package:flutter/material.dart';

class DictionaryCard extends StatefulWidget {
  const DictionaryCard({
    super.key,
    required this.result,
    this.maxMeanings,
    this.showHeader = true,
  });

  final DictionaryResult result;
  final int? maxMeanings;
  final bool showHeader;

  factory DictionaryCard.fromVocabulary(Vocabulary vocab, {Key? key}) {
    return DictionaryCard(
      key: key,
      result: DictionaryResult(
        word: vocab.word,
        phonetic: vocab.phonetic,
        meanings: vocab.meanings,
        synonyms: const [],
        antonyms: const [],
      ),
    );
  }

  @override
  State<DictionaryCard> createState() => _DictionaryCardState();
}

class _DictionaryCardState extends State<DictionaryCard> {
  int _tab = 0;

  List<String> get _synonyms {
    final set = <String>{...widget.result.synonyms};
    for (final m in widget.result.meanings) {
      set.addAll(m.synonyms);
    }
    final list = set.toList()..sort();
    return list;
  }

  List<String> get _antonyms {
    final set = <String>{...widget.result.antonyms};
    for (final m in widget.result.meanings) {
      set.addAll(m.antonyms);
    }
    final list = set.toList()..sort();
    return list;
  }

  List<Meaning> get _meanings {
    final all = widget.result.meanings;
    final max = widget.maxMeanings;
    if (max == null) return all;
    return all.take(max).toList();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.showHeader) ...[
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 8,
                      children: [
                        Text(
                          widget.result.word,
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        if (widget.result.phonetic != null)
                          Text(
                            widget.result.phonetic!,
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: Colors.grey.shade600,
                                  fontStyle: FontStyle.italic,
                                ),
                          ),
                      ],
                    ),
                  ),
                  PronunciationButton(
                    audioUrl: widget.result.audioUrl,
                    word: widget.result.word,
                  ),
                ],
              ),
              const SizedBox(height: 12),
            ],
            SegmentedButton<int>(
              showSelectedIcon: false,
              segments: const [
                ButtonSegment(value: 0, label: Text('Meanings')),
                ButtonSegment(value: 1, label: Text('Synonyms')),
                ButtonSegment(value: 2, label: Text('Antonyms')),
              ],
              selected: {_tab},
              onSelectionChanged: (s) => setState(() => _tab = s.first),
              style: ButtonStyle(
                visualDensity: VisualDensity.compact,
                textStyle: WidgetStatePropertyAll(
                  Theme.of(context).textTheme.labelMedium,
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (_tab == 0) _buildMeanings(context, scheme),
            if (_tab == 1) _buildRelated(context, _synonyms, 'No synonyms found.'),
            if (_tab == 2) _buildRelated(context, _antonyms, 'No antonyms found.'),
          ],
        ),
      ),
    );
  }

  Widget _buildMeanings(BuildContext context, ColorScheme scheme) {
    if (_meanings.isEmpty) {
      return Text(
        'No detailed definitions yet.',
        style: TextStyle(color: Colors.grey.shade600),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: _meanings
          .map(
            (m) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (m.partOfSpeech != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: scheme.primaryContainer.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        m.partOfSpeech!,
                        style: TextStyle(
                          color: scheme.primary,
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
            ),
          )
          .toList(),
    );
  }

  Widget _buildRelated(BuildContext context, List<String> words, String empty) {
    if (words.isEmpty) {
      return Text(empty, style: TextStyle(color: Colors.grey.shade600));
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: words
          .map(
            (w) => Chip(
              label: Text(w),
              visualDensity: VisualDensity.compact,
              backgroundColor:
                  Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.45),
            ),
          )
          .toList(),
    );
  }
}
