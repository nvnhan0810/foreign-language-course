import 'package:flutter/foundation.dart';

/// Bridges FCM / deep links → WebView navigation.
class WebAppNavigationBridge extends ChangeNotifier {
  void Function(String path)? _navigate;

  void attach(void Function(String path) navigate) {
    _navigate = navigate;
  }

  void detach() {
    _navigate = null;
  }

  void navigateToPath(String path) {
    final handler = _navigate;
    if (handler == null) {
      if (kDebugMode) {
        debugPrint('[WebApp] navigation queued before attach: $path');
      }
      return;
    }
    handler(path);
  }
}
