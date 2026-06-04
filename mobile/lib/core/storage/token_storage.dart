import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _keyToken = 'flc_token';
  static const _keyEmail = 'flc_email';
  static const _keyName = 'flc_name';

  Future<String?> getToken() => _storage.read(key: _keyToken);

  Future<void> saveSession({
    required String token,
    String? email,
    String? name,
  }) async {
    await _storage.write(key: _keyToken, value: token);
    if (email != null) await _storage.write(key: _keyEmail, value: email);
    if (name != null) await _storage.write(key: _keyName, value: name);
  }

  Future<void> clear() async {
    await _storage.deleteAll();
  }

  Future<String?> get email => _storage.read(key: _keyEmail);
  Future<String?> get name => _storage.read(key: _keyName);
}
