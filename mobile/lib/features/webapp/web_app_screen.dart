import 'dart:async';

import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/features/webapp/web_app_navigation.dart';
import 'package:flc_mobile/init_dependencies.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

class WebAppScreen extends StatefulWidget {
  const WebAppScreen({super.key, required this.navigationBridge});

  final WebAppNavigationBridge navigationBridge;

  @override
  State<WebAppScreen> createState() => _WebAppScreenState();
}

class _WebAppScreenState extends State<WebAppScreen> {
  late final WebViewController _controller;
  late final Uri _baseUri;
  var _loading = true;
  var _loadError = false;

  static const _oauthHosts = {
    'accounts.google.com',
    'www.google.com',
  };

  @override
  void initState() {
    super.initState();
    _baseUri = _initialAppUri();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setUserAgent('FLCApp/1.0 (${defaultTargetPlatform.name})')
      ..addJavaScriptChannel(
        'FlcNative',
        onMessageReceived: (message) {
          unawaited(appFlcWebBridge.onWebMessage(message.message));
        },
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) => setState(() {
            _loading = true;
            _loadError = false;
          }),
          onPageFinished: (_) {
            setState(() => _loading = false);
            unawaited(appFcmTokenRegistrar.emitFcmToken());
          },
          onWebResourceError: (_) => setState(() {
            _loading = false;
            _loadError = true;
          }),
          onNavigationRequest: _onNavigationRequest,
        ),
      )
      ..loadRequest(_baseUri);

    appFlcWebBridge.attach(_controller);
    widget.navigationBridge.attach(_openPath);
  }

  Uri _initialAppUri() {
    final base = Uri.parse(webAppUrl);
    return _withFlcApp(base);
  }

  Uri _withFlcApp(Uri uri) {
    final params = Map<String, String>.from(uri.queryParameters);
    params['flc_app'] = '1';
    return uri.replace(queryParameters: params);
  }

  @override
  void dispose() {
    appFlcWebBridge.detach();
    widget.navigationBridge.detach();
    super.dispose();
  }

  NavigationDecision _onNavigationRequest(NavigationRequest request) {
    final uri = Uri.tryParse(request.url);
    if (uri == null) {
      return NavigationDecision.navigate;
    }

    if (_shouldOpenExternally(uri)) {
      unawaited(launchUrl(uri, mode: LaunchMode.externalApplication));
      return NavigationDecision.prevent;
    }

    return NavigationDecision.navigate;
  }

  bool _shouldOpenExternally(Uri uri) {
    if (!uri.hasScheme || uri.scheme == 'about') {
      return false;
    }

    if (uri.scheme != 'http' && uri.scheme != 'https') {
      return true;
    }

    final host = uri.host.toLowerCase();
    if (host.isEmpty || host == _baseUri.host.toLowerCase()) {
      return false;
    }

    if (_oauthHosts.contains(host)) {
      return false;
    }

    return true;
  }

  void _openPath(String path) {
    final base = webAppUrl.replaceAll(RegExp(r'/$'), '');
    final normalized = path.startsWith('/') ? path : '/$path';
    _controller.loadRequest(_withFlcApp(Uri.parse('$base$normalized')));
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _loadError = false;
    });
    await _controller.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          if (_loadError)
            Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('Không tải được trang web.'),
                    const SizedBox(height: 8),
                    Text(
                      webAppUrl,
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _reload,
                      child: const Text('Thử lại'),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
