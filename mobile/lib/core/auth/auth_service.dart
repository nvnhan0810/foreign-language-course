import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/storage/token_storage.dart';
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';

class AuthService {
  AuthService(this._tokenStorage);

  final TokenStorage _tokenStorage;

  Future<bool> isLoggedIn() async {
    final token = await _tokenStorage.getToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> loginWithGoogle() async {
    final startUrl =
        '$apiBaseUrl/auth/google/redirect?redirect_uri=${Uri.encodeComponent(oauthRedirectUri)}';

    final result = await FlutterWebAuth2.authenticate(
      url: startUrl,
      callbackUrlScheme: 'flc',
    );

    final parsed = Uri.parse(result);
    final error = parsed.queryParameters['error'];
    if (error != null && error.isNotEmpty) {
      throw Exception(error);
    }

    final token = parsed.queryParameters['token'];
    if (token == null || token.isEmpty) {
      throw Exception('Không nhận được token từ server.');
    }

    await _tokenStorage.save(
      token: token,
      email: parsed.queryParameters['email'],
      name: parsed.queryParameters['name'],
    );
  }

  Future<void> logout() async {
    await _tokenStorage.clear();
  }
}
