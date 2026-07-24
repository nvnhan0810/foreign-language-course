import 'package:flc_mobile/config/app_config.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/core/theme/theme_mode_provider.dart';
import 'package:flc_mobile/core/theme/theme_storage.dart';
import 'package:flc_mobile/core/webview/web_app_navigation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

  String get _themeParam =>
      ref.read(themePreferenceProvider).storageValue;

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

  Uri _withAppParams(Uri uri) {
    final params = Map<String, String>.from(uri.queryParameters);
    params['flc_app'] = '1';
    params['flc_theme'] = _themeParam;
    return uri.replace(queryParameters: params);
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
        ..setBackgroundColor(Theme.of(context).scaffoldBackgroundColor)
        ..setNavigationDelegate(
          NavigationDelegate(
            onPageStarted: (_) {
              if (mounted) setState(() => _loading = true);
            },
            onPageFinished: (url) async {
              if (!mounted) return;
              setState(() => _loading = false);
              await _syncWebChrome();
              await _onUrl(url);
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
      await controller.loadRequest(_withAppParams(Uri.parse(handoff)));

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

  Future<void> _syncWebChrome() async {
    final controller = _controller;
    if (controller == null || !mounted) return;

    final padding = MediaQuery.paddingOf(context);
    final theme = ref.read(themePreferenceProvider).storageValue;
    final brightness = Theme.of(context).brightness;
    final isDark = brightness == Brightness.dark;

    SystemChrome.setSystemUIOverlayStyle(
      SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness:
            isDark ? Brightness.light : Brightness.dark,
        statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
        systemNavigationBarColor:
            isDark ? const Color(0xFF1A2332) : Colors.white,
        systemNavigationBarIconBrightness:
            isDark ? Brightness.light : Brightness.dark,
      ),
    );

    final sat = padding.top.toStringAsFixed(2);
    final sab = padding.bottom.toStringAsFixed(2);
    final sal = padding.left.toStringAsFixed(2);
    final sar = padding.right.toStringAsFixed(2);

    await controller.runJavaScript('''
(function () {
  var root = document.documentElement;
  root.style.setProperty('--sat', '${sat}px');
  root.style.setProperty('--sab', '${sab}px');
  root.style.setProperty('--sal', '${sal}px');
  root.style.setProperty('--sar', '${sar}px');
  try {
    localStorage.setItem('flc-theme', '$theme');
  } catch (e) {}
  var dark = '$theme' === 'dark' || ('$theme' === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  root.dataset.theme = dark ? 'dark' : 'light';
  var meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', dark ? '#1a2332' : '#4361ee');
  document.cookie = 'flc_theme=$theme; path=/; max-age=31536000; SameSite=Lax';
})();
''');
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
        await _bootstrap();
        return;
      }

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
    await controller.loadRequest(_withAppParams(resolveWebAppUri(pathOrUrl)));
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

    ref.listen<ThemePreference>(themePreferenceProvider, (prev, next) {
      if (prev == next) return;
      _syncWebChrome();
    });

    final scaffoldBg = Theme.of(context).scaffoldBackgroundColor;

    if (_bootstrapping && _controller == null) {
      return Scaffold(
        backgroundColor: scaffoldBg,
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null && _controller == null) {
      return Scaffold(
        backgroundColor: scaffoldBg,
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

    // Edge-to-edge WebView: no Flutter SafeArea (that caused black bars).
    // Safe insets are pushed into CSS --sat/--sab via _syncWebChrome.
    return Scaffold(
      backgroundColor: scaffoldBg,
      body: Stack(
        fit: StackFit.expand,
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
    );
  }
}
