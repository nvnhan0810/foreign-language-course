import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flc_mobile/core/firebase/firebase_bootstrap.dart';
import 'package:flc_mobile/core/notification/local_notifications_service.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await ensureFirebaseApp();
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((ref) {
  return PushNotificationService(ref);
});

class PushNotificationService {
  PushNotificationService(this._ref);

  final Ref _ref;
  final LocalNotificationsService _localNoti = LocalNotificationsService.instance;
  bool _initialized = false;

  Future<void> initialize(GoRouter router) async {
    if (_initialized) return;

    if (!isFirebaseConfigured) {
      debugPrint('Push: Firebase not configured — skipping FCM.');
      return;
    }

    try {
      await ensureFirebaseApp();
      await _localNoti.init();

      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      final messaging = FirebaseMessaging.instance;
      // iOS + cross-platform FCM permission dialog.
      await messaging.requestPermission(alert: true, badge: true, sound: true);

      FirebaseMessaging.onMessage.listen(_showForegroundNotification);

      FirebaseMessaging.onMessageOpenedApp.listen((message) {
        _handleNotificationNavigation(router, message);
      });

      _localNoti.onTap.listen((data) {
        final route = _routeFromData(data);
        if (route != null) {
          router.go(route);
        }
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        _pendingRoute = _routeFromMessage(initial);
      }

      _initialized = true;
    } catch (e, st) {
      debugPrint('Push: Firebase init failed (app continues without FCM): $e\n$st');
    }
  }

  static String? _pendingRoute;

  static String? consumePendingRoute() {
    final route = _pendingRoute;
    _pendingRoute = null;
    return route;
  }

  Future<void> syncTokenWithBackend() async {
    if (!_initialized) return;

    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null || token.isEmpty) return;

      final platform = Platform.isIOS ? 'ios' : 'android';
      await _ref.read(flcApiProvider).registerPushToken(token: token, platform: platform);
    } catch (e) {
      debugPrint('Push: register token failed: $e');
    }
  }

  Future<void> unregisterToken() async {
    if (!_initialized) return;

    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        await _ref.read(flcApiProvider).deletePushToken(token);
      }
      await FirebaseMessaging.instance.deleteToken();
    } catch (e) {
      debugPrint('Push: unregister token failed: $e');
    }
  }

  void applyPendingNavigation(GoRouter router) {
    final route = consumePendingRoute();
    if (route != null) {
      router.go(route);
    }
  }

  void _handleNotificationNavigation(GoRouter router, RemoteMessage message) {
    final route = _routeFromMessage(message);
    if (route != null) {
      router.go(route);
    }
  }

  void _showForegroundNotification(RemoteMessage message) {
    final n = message.notification;
    final title = (n?.title?.trim().isNotEmpty ?? false)
        ? n!.title!.trim()
        : (message.data['title']?.toString().trim() ?? '');
    final body = (n?.body?.trim().isNotEmpty ?? false)
        ? n!.body!.trim()
        : (message.data['body']?.toString().trim() ?? '');
    if (title.isEmpty && body.isEmpty) return;

    _localNoti.show(
      title: title.isNotEmpty ? title : 'Foreign Learner',
      body: body.isNotEmpty ? body : 'Vocab quiz reminder',
      payload: message.data.isNotEmpty ? jsonEncode(message.data) : null,
    );
  }

  static String? _routeFromMessage(RemoteMessage message) => _routeFromData(message.data);

  static String? _routeFromData(Map<String, dynamic> data) {
    if (data['type'] == 'vocab_quiz') {
      return '/home/quiz?autostart=1';
    }

    final route = data['route'];
    if (route is String && route.isNotEmpty) {
      final autostart = data['autostart'] == '1' ? '?autostart=1' : '';
      return route.contains('?') ? route : '$route$autostart';
    }

    return null;
  }
}
