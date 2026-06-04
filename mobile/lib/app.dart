import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/core/push/push_notification_service.dart';
import 'package:flc_mobile/core/theme/app_theme.dart';
import 'package:flc_mobile/router/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class FlcApp extends ConsumerStatefulWidget {
  const FlcApp({super.key});

  @override
  ConsumerState<FlcApp> createState() => _FlcAppState();
}

class _FlcAppState extends ConsumerState<FlcApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _setupPush());
  }

  Future<void> _setupPush() async {
    try {
      final router = ref.read(routerProvider);
      final push = ref.read(pushNotificationServiceProvider);
      await push.initialize(router);
      push.applyPendingNavigation(router);

      final loggedIn = await ref.read(authServiceProvider).isLoggedIn();
      if (loggedIn) {
        await push.syncTokenWithBackend();
      }
    } catch (e, st) {
      debugPrint('Push setup failed: $e\n$st');
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<bool>>(authStateProvider, (previous, next) {
      next.whenData((loggedIn) {
        if (loggedIn) {
          ref.read(pushNotificationServiceProvider).syncTokenWithBackend();
        }
      });
    });

    final router = ref.watch(routerProvider);
    return MaterialApp.router(
      title: 'Foreign Learner',
      theme: AppTheme.light(),
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
