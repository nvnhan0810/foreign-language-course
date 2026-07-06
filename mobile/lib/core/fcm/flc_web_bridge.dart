import 'dart:convert';
import 'dart:io';

import 'package:flc_mobile/core/fcm/fcm_service.dart';
import 'package:flutter/foundation.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Sends FCM token to the web layer via DOM event — web app handles API persistence.
class FlcWebBridge {
  FlcWebBridge({required FcmService fcm}) : _fcm = fcm;

  static const fcmTokenEvent = 'flc:fcm-token';
  static const _requestFcmToken = 'request-fcm-token';

  final FcmService _fcm;
  WebViewController? _controller;

  void attach(WebViewController controller) {
    _controller = controller;
  }

  void detach() {
    _controller = null;
  }

  Future<void> onWebMessage(String message) async {
    try {
      final data = jsonDecode(message);
      if (data is! Map) return;
      if (data['type'] == _requestFcmToken) {
        await emitFcmToken();
      }
    } catch (e) {
      if (kDebugMode) debugPrint('[FLC_BRIDGE] web message ignored: $e');
    }
  }

  Future<void> emitFcmToken() async {
    final controller = _controller;
    if (controller == null) {
      if (kDebugMode) debugPrint('[FLC_BRIDGE] skip — WebView not attached');
      return;
    }

    final token = await _fcm.getToken();
    if (token == null || token.trim().isEmpty) {
      if (kDebugMode) debugPrint('[FLC_BRIDGE] skip — FCM token empty');
      return;
    }

    final platform = Platform.isIOS ? 'ios' : 'android';
    final detail = jsonEncode({
      'token': token.trim(),
      'platform': platform,
    });

    await controller.runJavaScript(
      "window.dispatchEvent(new CustomEvent('$fcmTokenEvent',{detail:$detail}));",
    );

    if (kDebugMode) {
      debugPrint('[FLC_BRIDGE] emitted $fcmTokenEvent ($platform, ${token.substring(0, 8)}…)');
    }
  }
}
