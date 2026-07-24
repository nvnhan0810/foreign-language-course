import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/core/webview/web_app_navigation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';

class WebAppScreen extends ConsumerStatefulWidget {
  const WebAppScreen({super.key, this.initialPath});

  /// Optional deep-link path (e.g. `/home/quiz/play?autostart=1`).
  final String? initialPath;

  @override
  ConsumerState<WebAppScreen> createState() => _WebAppScreenState();
}

class _WebAppScreenState extends ConsumerState<WebAppScreen> {
  WebViewController? _controller;
  var _loading = true;
  var _bootstrapping = true;
  String? _error;
  var _sessionEstablished = false;
  var _signingOut = false;

  Uri get _webOrigin => Uri.parse(webAppUrl);

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void didUpdateWidget(WebAppScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialPath != null &&
        widget.initialPath != oldWidget.initialPath &&
        widget.initialPath!.isNotEmpty) {
      _navigateWeb(widget.initialPath!);
    }
  }

  Future<void> _bootstrap({String? nextPath}) async {
    setState(() {
      _bootstrapping = true;
      _loading = true;
      _error = null;
      _sessionEstablished = false;
    });

    try {
      final next = nextPath ?? widget.initialPath;
      final handoff = await ref.read(authServiceProvider).mintWebviewHandoffUrl(
            next: next != null ? mapAppPathToWeb(next) : null,
          );

      final controller = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setNavigationDelegate(
          NavigationDelegate(
            onPageStarted: (_) {
              if (mounted) setState(() => _loading = true);
            },
            onPageFinished: (url) {
              if (!mounted) return;
              setState(() => _loading = false);
              _onUrl(url);
            },
            onNavigationRequest: (request) {
              if (_isExternal(request.url)) {
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
            onWebResourceError: (error) {
              if (!mounted || _sessionEstablished) return;
              setState(() {
                _error = error.description;
                _loading = false;
                _bootstrapping = false;
              });
            },
          ),
        );

      final baseUa = await controller.getUserAgent() ?? '';
      await controller.setUserAgent(
        baseUa.contains('FLCApp/') ? baseUa : '$baseUa FLCApp/1.0',
      );

      _controller = controller;
      await controller.loadRequest(Uri.parse(handoff));

      if (mounted) {
        setState(() => _bootstrapping = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _bootstrapping = false;
          _loading = false;
        });
      }
    }
  }

  bool _isOurHost(Uri uri) {
    return uri.host == _webOrigin.host;
  }

  bool _isExternal(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null || !uri.hasScheme) return false;
    if (uri.scheme != 'http' && uri.scheme != 'https') return true;
    return !_isOurHost(uri);
  }

  bool _isLoginUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null || !_isOurHost(uri)) return false;
    final path = uri.path.replaceAll(RegExp(r'/+$'), '');
    return path == '/login' || path.endsWith('/login');
  }

  bool _isAuthedAppUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null || !_isOurHost(uri)) return false;
    final path = uri.path;
    return path.startsWith('/home') ||
        path.startsWith('/listening') ||
        path.contains('webview-handoff');
  }

  Future<void> _onUrl(String url) async {
    if (_isLoginUrl(url)) {
      final uri = Uri.tryParse(url);
      final intentionalLogout = uri?.queryParameters['flc_logout'] == '1';

      if (intentionalLogout) {
        await _handleWebLogout();
        return;
      }

      if (_sessionEstablished) {
        // Laravel session expired — remint from Sanctum token (no Google again).
        await _bootstrap();
        return;
      }

      // Handoff failed (expired/invalid) — keep Sanctum token for retry.
      if (mounted) {
        setState(() {
          _error = 'Could not open web session. Tap Retry.';
          _controller = null;
          _bootstrapping = false;
          _loading = false;
        });
      }
      return;
    }

    if (_isAuthedAppUrl(url)) {
      _sessionEstablished = true;
    }
  }

  Future<void> _handleWebLogout() async {
    if (_signingOut) return;
    _signingOut = true;
    try {
      await ref.read(authServiceProvider).logout();
      ref.invalidate(authStateProvider);
      if (mounted) context.go('/login');
    } finally {
      _signingOut = false;
    }
  }

  Future<void> _navigateWeb(String pathOrUrl) async {
    final controller = _controller;
    if (controller == null || !_sessionEstablished) {
      await _bootstrap(nextPath: pathOrUrl);
      return;
    }
    await controller.loadRequest(resolveWebAppUri(pathOrUrl));
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<String?>(webAppNavigateProvider, (prev, next) {
      if (next == null || next.isEmpty) return;
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        await _navigateWeb(next);
        ref.read(webAppNavigateProvider.notifier).state = null;
      });
    });

    if (_bootstrapping && _controller == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null && _controller == null) {
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => _bootstrap(),
                  child: const Text('Retry'),
                ),
                TextButton(
                  onPressed: () async {
                    await ref.read(authServiceProvider).logout();
                    ref.invalidate(authStateProvider);
                    if (context.mounted) context.go('/login');
                  },
                  child: const Text('Sign out'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            if (_controller != null) WebViewWidget(controller: _controller!),
            if (_loading)
              const Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: LinearProgressIndicator(minHeight: 2),
              ),
          ],
        ),
      ),
    );
  }
}
