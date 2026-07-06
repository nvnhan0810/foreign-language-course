import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

class MediaListScreen extends ConsumerStatefulWidget {
  const MediaListScreen({super.key});

  @override
  ConsumerState<MediaListScreen> createState() => _MediaListScreenState();
}

class _MediaListScreenState extends ConsumerState<MediaListScreen> {
  List<MediaItem>? _items;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await ref.read(flcApiProvider).listMedia();
      setState(() => _items = items);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            TextButton(onPressed: _load, child: const Text('Thử lại')),
          ],
        ),
      );
    }

    final items = _items ?? [];
    if (items.isEmpty) {
      return const Center(child: Text('Chưa có media. Thêm từ extension hoặc admin.'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, i) {
          final m = items[i];
          return Card(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  m.isYoutube ? Icons.play_circle : Icons.audiotrack,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              title: Text(m.title, style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Text(
                      m.type.toUpperCase(),
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                    const SizedBox(width: 8),
                    _DifficultyChip(label: m.difficultyLabel),
                  ],
                ),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/media/${m.id}', extra: m),
            ),
          );
        },
      ),
    );
  }
}

class _DifficultyChip extends StatelessWidget {
  const _DifficultyChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.secondaryContainer.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          color: Theme.of(context).colorScheme.onSecondaryContainer,
        ),
      ),
    );
  }
}
