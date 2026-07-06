import 'package:flutter_dotenv/flutter_dotenv.dart';

/// User-facing web app (Blade UI) loaded in WebView.
String get webAppUrl {
  final fromEnv = dotenv.env['WEBAPP_URL']?.trim();
  if (fromEnv != null && fromEnv.isNotEmpty) {
    return fromEnv;
  }
  const fromDefine = String.fromEnvironment('WEBAPP_URL');
  if (fromDefine.isNotEmpty) {
    return fromDefine;
  }
  return 'https://flc.nvnhan0810.com';
}
