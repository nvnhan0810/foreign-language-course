import 'dart:async';

import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/core/theme/app_theme.dart';
import 'package:flc_mobile/init_dependencies.dart';
import 'package:flc_mobile/router/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class FlcApp extends ConsumerStatefulWidget {
  const FlcApp({super.key});

  @override
  ConsumerState<FlcApp> createState() => _FlcAppState();
}

class _FlcAppState extends ConsumerState<FlcApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      appFcmRedirectCoordinator.start(ref.read(routerProvider));
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      final loggedIn = ref.read(authStateProvider).valueOrNull ?? false;
      if (loggedIn) {
        unawaited(appFcmTokenRegistrar.registerIfLoggedIn());
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<bool>>(authStateProvider, (previous, next) {
      next.whenData((loggedIn) {
        if (!loggedIn) return;
        unawaited(appFcmTokenRegistrar.registerIfLoggedIn());
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
