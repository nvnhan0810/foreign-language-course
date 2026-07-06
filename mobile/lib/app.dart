import 'package:flc_mobile/core/theme/app_theme.dart';
import 'package:flc_mobile/features/webapp/web_app_navigation.dart';
import 'package:flc_mobile/features/webapp/web_app_screen.dart';
import 'package:flc_mobile/init_dependencies.dart';
import 'package:flutter/material.dart';

class FlcApp extends StatefulWidget {
  const FlcApp({super.key});

  @override
  State<FlcApp> createState() => _FlcAppState();
}

class _FlcAppState extends State<FlcApp> with WidgetsBindingObserver {
  final _webNavigation = WebAppNavigationBridge();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      appFcmRedirectCoordinator.start(_webNavigation.navigateToPath);
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      appFcmTokenRegistrar.emitFcmToken();
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Foreign Learner',
      theme: AppTheme.light(),
      debugShowCheckedModeBanner: false,
      home: WebAppScreen(navigationBridge: _webNavigation),
    );
  }
}
