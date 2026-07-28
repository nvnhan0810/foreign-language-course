import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/api/flc_api.dart';
import 'package:flc_mobile/core/storage/token_storage.dart';
import 'package:flutter/services.dart';
import 'package:flutter_web_auth_2/flutter_web_auth_2.dart';

/// User dismissed the Google / OAuth browser sheet (back / cancel).
class AuthCanceledException implements Exception {
  const AuthCanceledException();

  @override
  String toString() => 'AuthCanceledException';
}

class AuthService {
  AuthService(this._tokenStorage, this._api);

  final TokenStorage _tokenStorage;
  final FlcApi _api;

  Future<bool> isLoggedIn() async {
    final token = await _tokenStorage.getToken();
    return token != null && token.isNotEmpty;
  }

  /// Returns `true` when login succeeded. Throws [AuthCanceledException] if the
  /// user cancels so the UI can retry without showing a PlatformException.
  Future<bool> loginWithGoogle() async {
    final startUrl =
        '$apiBaseUrl/auth/google/redirect?redirect_uri=${Uri.encodeComponent(oauthRedirectUri)}';

    final String result;
    try {
      result = await FlutterWebAuth2.authenticate(
        url: startUrl,
        callbackUrlScheme: 'flc',
      );
    } on PlatformException catch (e) {
      // Android Custom Tabs / iOS ASWebAuthenticationSession cancel.
      if (e.code == 'CANCELED') {
        throw const AuthCanceledException();
      }
      rethrow;
    }

    final parsed = Uri.parse(result);
    final error = parsed.queryParameters['error'];
    if (error != null && error.isNotEmpty) {
      throw Exception(error);
    }

    final token = parsed.queryParameters['token'];
    if (token == null || token.isEmpty) {
      throw Exception('No token received from server.');
    }

    await _tokenStorage.save(
      token: token,
      email: parsed.queryParameters['email'],
      name: parsed.queryParameters['name'],
    );
    return true;
  }

  Future<String> mintWebviewHandoffUrl({String? next}) {
    return _api.mintWebviewHandoffUrl(next: next);
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } on ApiException {
      // Token may already be invalid; still clear local state.
    } catch (_) {}
    await _tokenStorage.clear();
  }
}
