import 'package:flc_mobile/features/lookup/lookup_screen.dart';
import 'package:flc_mobile/features/media/media_list_screen.dart';
import 'package:flc_mobile/features/profile/profile_screen.dart';
import 'package:flc_mobile/features/quiz/vocab_quiz_screen.dart';
import 'package:flc_mobile/features/vocab/vocab_list_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  static const _tabs = [
    _Tab('/home/lookup', Icons.menu_book, 'Lookup'),
    _Tab('/home/vocab', Icons.bookmark, 'Vocabulary'),
    _Tab('/home/media', Icons.headphones, 'Listen'),
    _Tab('/home/quiz', Icons.quiz, 'Quiz'),
    _Tab('/home/profile', Icons.person, 'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).uri.toString();
    _index = _tabs.indexWhere((t) => location.startsWith(t.path));
    if (_index < 0) _index = 0;

    final isProfile = _tabs[_index].path == '/home/profile';

    return Scaffold(
      appBar: isProfile
          ? null
          : AppBar(
              title: Text(_tabs[_index].label),
            ),
      body: widget.child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => context.go(_tabs[i].path),
        destinations: _tabs
            .map((t) => NavigationDestination(icon: Icon(t.icon), label: t.label))
            .toList(),
      ),
    );
  }
}

class _Tab {
  const _Tab(this.path, this.icon, this.label);
  final String path;
  final IconData icon;
  final String label;
}

class HomeTabLookup extends StatelessWidget {
  const HomeTabLookup({super.key});
  @override
  Widget build(BuildContext context) => const LookupScreen();
}

class HomeTabVocab extends StatelessWidget {
  const HomeTabVocab({super.key});
  @override
  Widget build(BuildContext context) => const VocabListScreen();
}

class HomeTabMedia extends StatelessWidget {
  const HomeTabMedia({super.key});
  @override
  Widget build(BuildContext context) => const MediaListScreen();
}

class HomeTabQuiz extends StatelessWidget {
  const HomeTabQuiz({super.key, this.autostart = false});

  final bool autostart;

  @override
  Widget build(BuildContext context) => VocabQuizScreen(autostart: autostart);
}

class HomeTabProfile extends StatelessWidget {
  const HomeTabProfile({super.key});
  @override
  Widget build(BuildContext context) => const ProfileScreen();
}
