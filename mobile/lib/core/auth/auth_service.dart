import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/storage/token_storage.dart';
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';

class AuthService {
  AuthService(this._storage);

  final TokenStorage _storage;

  Future<bool> isLoggedIn() async {
    final token = await _storage.getToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> loginWithGoogle() async {
    final startUrl =
        '${apiBaseUrl.replaceAll(RegExp(r'/+$'), '')}/auth/google/redirect?'
        'redirect_uri=${Uri.encodeComponent(oauthRedirectUri)}';

    final result = await FlutterWebAuth2.authenticate(
      url: startUrl,
      callbackUrlScheme: 'flc',
    );

    final uri = Uri.parse(result);
    final error = uri.queryParameters['error'];
    if (error != null && error.isNotEmpty) {
      throw Exception(error);
    }

    final token = uri.queryParameters['token'];
    if (token == null || token.isEmpty) {
      throw Exception('Không nhận được token từ server.');
    }

    await _storage.saveSession(
      token: token,
      email: uri.queryParameters['email'],
      name: uri.queryParameters['name'],
    );
  }

  Future<void> logout() => _storage.clear();
}
