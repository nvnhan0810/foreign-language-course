import 'package:flc_mobile/widgets/game_mode_card.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class GamesHubScreen extends StatelessWidget {
  const GamesHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Text(
          'Play & learn',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
        ),
        const SizedBox(height: 16),
        GameModeCard(
          title: 'Quiz',
          description: 'Multiple-choice review of saved words',
          icon: Icons.quiz_outlined,
          tag: 'Play',
          onTap: () => context.push('/home/quiz/play?autostart=1'),
        ),
        const SizedBox(height: 12),
        GameModeCard(
          title: 'Word Puzzle',
          description: 'Scramble & more letter games',
          icon: Icons.extension_outlined,
          tag: 'Play',
          onTap: () => context.push('/home/puzzle'),
        ),
      ],
    );
  }
}
