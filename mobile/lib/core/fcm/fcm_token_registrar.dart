import 'dart:async';

import 'package:flc_mobile/core/fcm/fcm_service.dart';
import 'package:flc_mobile/core/fcm/flc_web_bridge.dart';
import 'package:flutter/foundation.dart';

class FcmTokenRegistrar {
  FcmTokenRegistrar({
    required FcmService fcm,
    required FlcWebBridge webBridge,
  })  : _fcm = fcm,
        _webBridge = webBridge;

  final FcmService _fcm;
  final FlcWebBridge _webBridge;

  StreamSubscription<String>? _sub;

  Future<void> start() async {
    _sub ??= _fcm.onTokenRefresh.listen((_) async {
      try {
        await emitFcmToken();
      } catch (e) {
        if (kDebugMode) debugPrint('[FCM_REGISTRAR] refresh emit ignored: $e');
      }
    });
  }

  Future<void> emitFcmToken() async {
    try {
      await _webBridge.emitFcmToken();
    } catch (e) {
      if (kDebugMode) debugPrint('[FCM_REGISTRAR] emit ignored: $e');
    }
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    _sub = null;
  }
}
