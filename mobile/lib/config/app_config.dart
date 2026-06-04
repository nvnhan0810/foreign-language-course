import 'package:flutter_dotenv/flutter_dotenv.dart';

/// API base URL from `.env` (`API_BASE_URL`).
/// Fallback: `--dart-define=API_BASE_URL=...` then default localhost.
String get apiBaseUrl {
  final fromEnv = dotenv.env['API_BASE_URL']?.trim();
  if (fromEnv != null && fromEnv.isNotEmpty) {
    return fromEnv;
  }
  const fromDefine = String.fromEnvironment('API_BASE_URL');
  if (fromDefine.isNotEmpty) {
    return fromDefine;
  }
  return 'http://localhost:8080/api';
}

const String oauthRedirectUri = 'flc://oauth-callback';
