import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/features/auth/login_screen.dart';
import 'package:flc_mobile/features/home/home_shell.dart';
import 'package:flc_mobile/features/listening/listening_quiz_screen.dart';
import 'package:flc_mobile/features/media/media_detail_screen.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authStateProvider);

  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/login',
    refreshListenable: _AuthRefresh(ref),
    redirect: (context, state) {
      if (auth.isLoading) return null;
      final loggedIn = auth.value == true;
      final onLogin = state.matchedLocation == '/login';
      if (!loggedIn && !onLogin) return '/login';
      if (loggedIn && onLogin) return '/home/lookup';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) => HomeShell(child: child),
        routes: [
          GoRoute(path: '/home/lookup', builder: (_, __) => const HomeTabLookup()),
          GoRoute(path: '/home/vocab', builder: (_, __) => const HomeTabVocab()),
          GoRoute(path: '/home/media', builder: (_, __) => const HomeTabMedia()),
          GoRoute(path: '/home/quiz', builder: (_, __) => const HomeTabQuiz()),
          GoRoute(path: '/home/profile', builder: (_, __) => const HomeTabProfile()),
        ],
      ),
      GoRoute(
        path: '/media/:id',
        builder: (context, state) {
          final id = int.parse(state.pathParameters['id']!);
          final extra = state.extra;
          return MediaDetailScreen(
            mediaId: id,
            initial: extra is MediaItem ? extra : null,
          );
        },
      ),
      GoRoute(
        path: '/listening/:id',
        builder: (context, state) {
          final id = int.parse(state.pathParameters['id']!);
          String? title;
          if (state.extra is Map) {
            final a = (state.extra as Map)['assessment'];
            if (a is ListeningAssessmentSummary) title = a.title;
          }
          return ListeningQuizScreen(assessmentId: id, title: title);
        },
      ),
    ],
  );
});

class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(this._ref) {
    _ref.listen(authStateProvider, (_, __) => notifyListeners());
  }
  final Ref _ref;
}
