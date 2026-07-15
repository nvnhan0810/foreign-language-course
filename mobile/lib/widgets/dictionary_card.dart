import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/pronunciation_button.dart';
import 'package:flutter/material.dart';

class DictionaryCard extends StatelessWidget {
  const DictionaryCard({
    super.key,
    required this.result,
    this.maxMeanings,
    this.showHeader = true,
    this.onRelatedWord,
  });

  final DictionaryResult result;
  final int? maxMeanings;
  final bool showHeader;
  final ValueChanged<String>? onRelatedWord;

  factory DictionaryCard.fromVocabulary(
    Vocabulary vocab, {
    Key? key,
    ValueChanged<String>? onRelatedWord,
  }) {
    return DictionaryCard(
      key: key,
      result: DictionaryResult(
        word: vocab.word,
        phonetic: vocab.phonetic,
        meanings: vocab.meanings,
        synonyms: const [],
        antonyms: const [],
      ),
      onRelatedWord: onRelatedWord,
    );
  }

  List<Meaning> get _meanings {
    final all = result.meanings;
    final max = maxMeanings;
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
            if (showHeader) ...[
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
            ],
            _buildMeanings(context, scheme),
          ],
        ),
      ),
    );
  }

  Widget _buildMeanings(BuildContext context, ColorScheme scheme) {
    if (_meanings.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'No detailed definitions yet.',
            style: TextStyle(color: Colors.grey.shade600),
          ),
          _relatedSection(context, 'Synonyms', result.synonyms),
          _relatedSection(context, 'Antonyms', result.antonyms),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < _meanings.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _meaningBlock(context, scheme, _meanings[i], i == 0),
          ),
      ],
    );
  }

  Widget _meaningBlock(
    BuildContext context,
    ColorScheme scheme,
    Meaning meaning,
    bool includeEntryRelated,
  ) {
    final synonyms = <String>{
      ...meaning.synonyms,
      if (includeEntryRelated) ...result.synonyms,
    }.toList()
      ..sort();
    final antonyms = <String>{
      ...meaning.antonyms,
      if (includeEntryRelated) ...result.antonyms,
    }.toList()
      ..sort();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (meaning.partOfSpeech != null)
          Container(
            margin: const EdgeInsets.only(bottom: 4),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: scheme.primaryContainer.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              meaning.partOfSpeech!,
              style: TextStyle(
                color: scheme.primary,
                fontStyle: FontStyle.italic,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        Text(meaning.definition),
        if (meaning.example != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              '"${meaning.example}"',
              style: TextStyle(
                color: Colors.grey.shade700,
                fontSize: 13,
              ),
            ),
          ),
        _relatedSection(context, 'Synonyms', synonyms),
        _relatedSection(context, 'Antonyms', antonyms),
      ],
    );
  }

  Widget _relatedSection(BuildContext context, String label, List<String> words) {
    if (words.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Colors.grey.shade600,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.4,
                ),
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: words
                .map(
                  (w) => ActionChip(
                    label: Text(w),
                    visualDensity: VisualDensity.compact,
                    backgroundColor:
                        Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.45),
                    onPressed: onRelatedWord == null ? null : () => onRelatedWord!(w),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}
