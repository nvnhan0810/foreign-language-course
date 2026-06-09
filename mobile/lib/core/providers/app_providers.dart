import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/api/flc_api.dart';
import 'package:flc_mobile/core/auth/auth_service.dart';
import 'package:flc_mobile/core/fcm/fcm_token_registrar.dart';
import 'package:flc_mobile/core/storage/token_storage.dart';
import 'package:flc_mobile/init_dependencies.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final tokenStorageProvider = Provider<TokenStorage>((ref) => appTokenStorage);

final apiClientProvider = Provider<ApiClient>((ref) => appApiClient);

final flcApiProvider = Provider<FlcApi>((ref) => appFlcApi);

final authServiceProvider = Provider<AuthService>((ref) => appAuthService);

final fcmTokenRegistrarProvider = Provider<FcmTokenRegistrar>((ref) => appFcmTokenRegistrar);

final authStateProvider = FutureProvider<bool>((ref) async {
  return ref.watch(authServiceProvider).isLoggedIn();
});

final profileProvider = FutureProvider<UserProfile>((ref) async {
  return ref.watch(flcApiProvider).getProfile();
});

final notificationSettingsProvider = FutureProvider<NotificationSettings>((ref) async {
  return ref.watch(flcApiProvider).getNotificationSettings();
});
