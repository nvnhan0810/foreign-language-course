import 'package:flutter/material.dart';

const mediaDifficultyFilters = <String?, String>{
  null: 'Tất cả',
  'beginner': 'Cơ bản',
  'intermediate': 'Trung cấp',
  'advanced': 'Nâng cao',
};

class DifficultyFilterBar extends StatelessWidget {
  const DifficultyFilterBar({
    super.key,
    required this.selected,
    required this.onSelected,
  });

  final String? selected;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: mediaDifficultyFilters.entries.map((entry) {
          final isSelected = selected == entry.key;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilterChip(
              label: Text(entry.value),
              selected: isSelected,
              onSelected: (_) => onSelected(entry.key),
              showCheckmark: false,
            ),
          );
        }).toList(),
      ),
    );
  }
}

Color difficultyColor(BuildContext context, String difficulty) {
  final scheme = Theme.of(context).colorScheme;
  return switch (difficulty) {
    'beginner' => scheme.tertiaryContainer,
    'advanced' => scheme.errorContainer,
    _ => scheme.primaryContainer,
  };
}

Color difficultyTextColor(BuildContext context, String difficulty) {
  final scheme = Theme.of(context).colorScheme;
  return switch (difficulty) {
    'beginner' => scheme.onTertiaryContainer,
    'advanced' => scheme.onErrorContainer,
    _ => scheme.onPrimaryContainer,
  };
}

class DifficultyChip extends StatelessWidget {
  const DifficultyChip({super.key, required this.label, required this.difficulty});

  final String label;
  final String difficulty;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: difficultyColor(context, difficulty).withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: difficultyTextColor(context, difficulty),
        ),
      ),
    );
  }
}
