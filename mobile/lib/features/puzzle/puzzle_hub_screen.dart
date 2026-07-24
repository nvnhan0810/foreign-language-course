import 'package:flc_mobile/widgets/game_mode_card.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class PuzzleHubScreen extends StatelessWidget {
  const PuzzleHubScreen({super.key});

  Future<void> _confirmExit(BuildContext context) async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Exit Word Puzzle?'),
        content: const Text("Your current round won't be saved."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Stay')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Exit')),
        ],
      ),
    );
    if (leave == true && context.mounted) {
      context.go('/home/quiz');
    }
  }

  void _comingSoon(BuildContext context, String mode) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$mode is coming soon.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Word Puzzle'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          tooltip: 'Exit',
          onPressed: () => _confirmExit(context),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          Text(
            'Choose a mode',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
          const SizedBox(height: 16),
          GameModeCard(
            title: 'Scramble',
            description: 'Unscramble letters vs. the clock',
            icon: Icons.shuffle,
            tag: 'Play',
            onTap: () => context.push('/home/puzzle/scramble?autostart=1'),
          ),
          const SizedBox(height: 12),
          GameModeCard(
            title: 'Wordle',
            description: 'Guess with color feedback',
            icon: Icons.grid_view_rounded,
            tag: 'Soon',
            muted: true,
            onTap: () => _comingSoon(context, 'Wordle'),
          ),
          const SizedBox(height: 12),
          GameModeCard(
            title: 'Hangman',
            description: 'Guess letters from a clue',
            icon: Icons.accessibility_new,
            tag: 'Soon',
            muted: true,
            onTap: () => _comingSoon(context, 'Hangman'),
          ),
          const SizedBox(height: 12),
          GameModeCard(
            title: 'Word Search',
            description: 'Find your words in a grid',
            icon: Icons.search,
            tag: 'Soon',
            muted: true,
            onTap: () => _comingSoon(context, 'Word Search'),
          ),
        ],
      ),
    );
  }
}
