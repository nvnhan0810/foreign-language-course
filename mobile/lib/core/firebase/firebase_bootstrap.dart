import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:flc_mobile/config/firebase_options.dart';
import 'package:flutter/foundation.dart';

/// Single Firebase bootstrap for the app (main + background isolate).
Future<FirebaseApp>? _initFuture;

/// Returns the default Firebase app, initializing once if needed.
///
/// On iOS/Android uses `GoogleService-Info.plist` / `google-services.json`.
/// Duplicate init (native default app already exists) is treated as success.
Future<FirebaseApp> ensureFirebaseApp() {
  return _initFuture ??= _initFirebaseOnce();
}

Future<FirebaseApp> _initFirebaseOnce() async {
  if (Firebase.apps.isNotEmpty) {
    return Firebase.app();
  }

  try {
    if (!kIsWeb && (Platform.isIOS || Platform.isAndroid)) {
      return await Firebase.initializeApp();
    }

    final options = FirebaseOptionsFromEnv.tryLoad();
    if (options == null) {
      throw StateError(
        'Firebase is not configured. Add mobile/.env FIREBASE_* or platform config files.',
      );
    }
    return await Firebase.initializeApp(options: options);
  } on FirebaseException catch (e) {
    if (e.code == 'duplicate-app') {
      return Firebase.app();
    }
    rethrow;
  }
}

/// True when [ensureFirebaseApp] can run (platform files or .env).
bool get isFirebaseConfigured {
  if (!kIsWeb && (Platform.isIOS || Platform.isAndroid)) {
    return true;
  }
  return FirebaseOptionsFromEnv.tryLoad() != null;
}
