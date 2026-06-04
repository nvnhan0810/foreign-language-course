import 'package:flc_mobile/app.dart';
import 'package:flc_mobile/core/firebase/firebase_bootstrap.dart';
import 'package:flc_mobile/core/notification/local_notifications_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await dotenv.load(fileName: '.env');
  } catch (_) {
    await dotenv.load(fileName: '.env.example');
  }

  if (isFirebaseConfigured) {
    try {
      await LocalNotificationsService.instance.init();
    } catch (_) {}
    await ensureFirebaseApp();
  }

  runApp(const ProviderScope(child: FlcApp()));
}
