import 'dart:async';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flc_mobile/core/api/flc_api.dart';
import 'package:flc_mobile/core/auth/auth_service.dart';
import 'package:flc_mobile/core/fcm/fcm_service.dart';
import 'package:flutter/foundation.dart';

class FcmTokenRegistrar {
  FcmTokenRegistrar({
    required FcmService fcm,
    required FlcApi api,
    required AuthService auth,
  })  : _fcm = fcm,
        _api = api,
        _auth = auth;

  final FcmService _fcm;
  final FlcApi _api;
  final AuthService _auth;

  StreamSubscription<String>? _sub;

  Future<void> start() async {
    try {
      await registerIfLoggedIn();
    } catch (e) {
      if (kDebugMode) debugPrint('[FCM_REGISTRAR] initial register ignored: $e');
    }

    _sub ??= _fcm.onTokenRefresh.listen((_) async {
      try {
        await registerIfLoggedIn();
      } catch (e) {
        if (kDebugMode) debugPrint('[FCM_REGISTRAR] refresh register ignored: $e');
      }
    });
  }

  Future<void> registerIfLoggedIn() async {
    if (!await _auth.isLoggedIn()) {
      if (kDebugMode) debugPrint('[FCM_REGISTRAR] skip — not logged in');
      return;
    }

    final token = await _fcm.getToken();
    if (token == null || token.trim().isEmpty) {
      if (kDebugMode) debugPrint('[FCM_REGISTRAR] skip — FCM token empty');
      return;
    }

    final platform = Platform.isIOS ? 'ios' : 'android';
    await _api.registerPushToken(token: token.trim(), platform: platform);

    if (kDebugMode) {
      debugPrint('[FCM_REGISTRAR] registered ($platform, ${token.substring(0, 8)}…)');
    }
  }

  Future<void> unregister() async {
    try {
      final token = await _fcm.getToken();
      if (token != null && token.isNotEmpty) {
        await _api.deletePushToken(token);
      }
      await FirebaseMessaging.instance.deleteToken();
    } catch (e) {
      if (kDebugMode) debugPrint('[FCM_REGISTRAR] unregister ignored: $e');
    }
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    _sub = null;
  }
}
