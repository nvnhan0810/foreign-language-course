import 'dart:async';

import 'package:flc_mobile/core/fcm/fcm_service.dart';
import 'package:flc_mobile/router/app_router.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

class FcmRedirectCoordinator {
  FcmRedirectCoordinator(this._fcm);

  final FcmService _fcm;
  StreamSubscription<String>? _sub;
  GoRouter? _router;
  String? _queued;
  Timer? _retryTimer;

  void start(GoRouter router) {
    _router = router;
    _sub ??= _fcm.routes.listen(_handleRoute);

    final pending = _fcm.consumePendingRoute();
    if (pending != null) {
      _handleRoute(pending);
    }
  }

  void _handleRoute(String route) {
    if (route.isEmpty) return;
    if (kDebugMode) debugPrint('[FCM_REDIRECT] route=$route');
    _queued = route;
    _drainQueueWithRetry();
  }

  void _drainQueueWithRetry() {
    _retryTimer?.cancel();
    var attempts = 0;
    _retryTimer = Timer.periodic(const Duration(milliseconds: 250), (timer) {
      attempts += 1;
      final route = _queued;
      final router = _router;
      final context = rootNavigatorKey.currentContext;

      if (route != null && router != null && context != null) {
        router.go(route);
        _queued = null;
        timer.cancel();
        return;
      }

      if (attempts >= 20) {
        timer.cancel();
      }
    });
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    _sub = null;
    _retryTimer?.cancel();
    _retryTimer = null;
  }
}
