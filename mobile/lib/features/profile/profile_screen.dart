import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

String _formatDate(DateTime dt) {
  final d = dt.toLocal();
  final dd = d.day.toString().padLeft(2, '0');
  final mm = d.month.toString().padLeft(2, '0');
  return '$dd/$mm/${d.year}';
}

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(profileProvider);

    return ColoredBox(
      color: Theme.of(context).scaffoldBackgroundColor,
      child: profileAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ProfileError(
          message: error.toString(),
          onRetry: () => ref.invalidate(profileProvider),
        ),
        data: (profile) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(profileProvider);
            await ref.read(profileProvider.future);
          },
          child: _ProfileContent(profile: profile, ref: ref),
        ),
      ),
    );
  }
}

class _ProfileError extends StatelessWidget {
  const _ProfileError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 16),
            Text(
              'Không tải được hồ sơ',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Thử lại')),
          ],
        ),
      ),
    );
  }
}

class _ProfileContent extends StatelessWidget {
  const _ProfileContent({required this.profile, required this.ref});

  final UserProfile profile;
  final WidgetRef ref;

  @override
  Widget build(BuildContext context) {
    final user = profile.user;
    final stats = profile.stats;

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverAppBar(
          expandedHeight: 200,
          pinned: true,
          flexibleSpace: FlexibleSpaceBar(
            background: IgnorePointer(
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Theme.of(context).colorScheme.primary,
                      Theme.of(context).colorScheme.primaryContainer,
                    ],
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const SizedBox(height: 40),
                    CircleAvatar(
                      radius: 40,
                      backgroundColor: Colors.white,
                      child: Text(
                        user.initials,
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.bold,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      user.name.isNotEmpty ? user.name : 'Người dùng',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      user.email,
                      style: const TextStyle(color: Colors.white70, fontSize: 14),
                    ),
                  ],
                ),
              ),
            ),
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.logout),
              tooltip: 'Đăng xuất',
              onPressed: () => _logout(context),
            ),
          ],
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Thống kê',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    _StatCard(
                      label: 'Từ vựng',
                      value: '${stats.vocabularyCount}',
                      icon: Icons.bookmark,
                    ),
                    const SizedBox(width: 16),
                    _StatCard(
                      label: 'Bài nghe',
                      value: '${stats.mediaCount}',
                      icon: Icons.headphones,
                    ),
                    const SizedBox(width: 16),
                    _StatCard(
                      label: 'Điểm TB',
                      value: stats.averageScoreLabel,
                      icon: Icons.star,
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                const _NotificationSettingsCard(),
                const SizedBox(height: 32),
                const Text(
                  'Lịch sử làm bài',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                if (profile.history.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                      child: Text(
                        'Chưa có bài làm nào.\nHãy thử quiz từ vựng hoặc bài nghe!',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (profile.history.isNotEmpty)
          SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, index) {
                final item = profile.history[index];
                return _HistoryTile(item: item);
              },
              childCount: profile.history.length,
            ),
          ),
        const SliverToBoxAdapter(child: SizedBox(height: 32)),
      ],
    );
  }

  Future<void> _logout(BuildContext context) async {
    try {
      await ref.read(fcmTokenRegistrarProvider).unregister();
    } catch (_) {}

    try {
      await ref.read(flcApiProvider).logout();
    } catch (_) {}

    await ref.read(authServiceProvider).logout();
    ref.invalidate(authStateProvider);
    await ref.read(authStateProvider.future);

    if (context.mounted) {
      context.go('/login');
    }
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Theme.of(context)
              .colorScheme
              .surfaceContainerHighest
              .withValues(alpha: 0.5),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          children: [
            Icon(icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 8),
            Text(
              value,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.item});

  final ProfileHistoryItem item;

  IconData _iconForType() {
    if (item.isListening) {
      return switch (item.type) {
        'exam' => Icons.school,
        'test' => Icons.headset,
        _ => Icons.quiz,
      };
    }
    return Icons.quiz;
  }

  @override
  Widget build(BuildContext context) {
    final dateLabel =
        item.completedAt != null ? _formatDate(item.completedAt!) : '';

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: 0,
      color: Theme.of(context)
          .colorScheme
          .surfaceContainerHighest
          .withValues(alpha: 0.5),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Theme.of(context).colorScheme.primaryContainer,
          child: Icon(
            _iconForType(),
            color: Theme.of(context).colorScheme.primary,
          ),
        ),
        title: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(dateLabel),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primary,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            item.scoreLabel,
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
        ),
      ),
    );
  }
}

class _NotificationSettingsCard extends ConsumerWidget {
  const _NotificationSettingsCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settingsAsync = ref.watch(notificationSettingsProvider);

    return settingsAsync.when(
      loading: () => const LinearProgressIndicator(),
      error: (_, __) => const SizedBox.shrink(),
      data: (settings) {
        final schedule = settings.reminderSchedule;
        final tz = schedule?['timezone'] ?? 'Asia/Ho_Chi_Minh';
        final midday = schedule?['midday'] ?? '11:00';
        final evening = schedule?['evening'] ?? '20:00';

        return Card(
          elevation: 0,
          color: Theme.of(context)
              .colorScheme
              .surfaceContainerHighest
              .withValues(alpha: 0.5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: SwitchListTile(
            title: const Text(
              'Nhắc quiz từ vựng',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Text(
              settings.globalVocabQuizPushEnabled
                  ? 'Lịch: $midday & $evening ($tz). Chạm noti mở thẳng Quiz.'
                  : 'Admin đã tắt nhắc push toàn hệ thống.',
            ),
            value: settings.isActive,
            onChanged: settings.globalVocabQuizPushEnabled
                ? (value) async {
                    await ref.read(flcApiProvider).updateNotificationSettings(
                          vocabQuizPushEnabled: value,
                        );
                    ref.invalidate(notificationSettingsProvider);
                    if (value) {
                      await ref.read(fcmTokenRegistrarProvider).registerIfLoggedIn();
                    }
                  }
                : null,
          ),
        );
      },
    );
  }
}
