import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/api/flc_api.dart';
import 'package:flc_mobile/core/auth/auth_service.dart';
import 'package:flc_mobile/core/storage/token_storage.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(ref.watch(tokenStorageProvider));
});

final flcApiProvider = Provider<FlcApi>((ref) => FlcApi(ref.watch(apiClientProvider)));

final authServiceProvider = Provider<AuthService>((ref) {
  return AuthService(ref.watch(tokenStorageProvider));
});

final authStateProvider = FutureProvider<bool>((ref) async {
  return ref.watch(authServiceProvider).isLoggedIn();
});

final profileProvider = FutureProvider<UserProfile>((ref) async {
  return ref.watch(flcApiProvider).getProfile();
});

final notificationSettingsProvider = FutureProvider<NotificationSettings>((ref) async {
  return ref.watch(flcApiProvider).getNotificationSettings();
});
