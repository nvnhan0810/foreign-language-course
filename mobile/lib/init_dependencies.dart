import 'package:flc_mobile/core/fcm/fcm_redirect_coordinator.dart';
import 'package:flc_mobile/core/fcm/fcm_service.dart';
import 'package:flc_mobile/core/fcm/fcm_token_registrar.dart';
import 'package:flc_mobile/core/fcm/flc_web_bridge.dart';
import 'package:flc_mobile/core/notification/local_notifications_service.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

late final FcmService appFcmService;
late final FlcWebBridge appFlcWebBridge;
late final FcmTokenRegistrar appFcmTokenRegistrar;
late final FcmRedirectCoordinator appFcmRedirectCoordinator;

Future<void> initDependencies() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await dotenv.load(fileName: '.env');
  } catch (_) {
    await dotenv.load(fileName: '.env.example');
  }

  appFcmService = FcmService(localNoti: LocalNotificationsService.instance);

  try {
    await LocalNotificationsService.instance.init();
  } catch (_) {}
  try {
    await appFcmService.init();
  } catch (_) {}

  appFlcWebBridge = FlcWebBridge(fcm: appFcmService);
  appFcmTokenRegistrar = FcmTokenRegistrar(
    fcm: appFcmService,
    webBridge: appFlcWebBridge,
  );
  appFcmRedirectCoordinator = FcmRedirectCoordinator(appFcmService);

  try {
    await appFcmTokenRegistrar.start();
  } catch (_) {}
}
