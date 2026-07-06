import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/difficulty_filter_bar.dart';
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
  String? _difficultyFilter;

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

  List<MediaItem> get _filteredItems {
    final items = _items ?? [];
    if (_difficultyFilter == null) return items;
    return items.where((m) => m.difficulty == _difficultyFilter).toList();
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

    final allItems = _items ?? [];
    if (allItems.isEmpty) {
      return const Center(child: Text('Chưa có media. Thêm từ extension hoặc admin.'));
    }

    final filtered = _filteredItems;
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DifficultyFilterBar(
          selected: _difficultyFilter,
          onSelected: (value) => setState(() => _difficultyFilter = value),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: filtered.isEmpty
                ? ListView(
                    children: [
                      const SizedBox(height: 80),
                      Center(
                        child: Text(
                          'Không có media ở mức độ này.',
                          style: TextStyle(color: muted),
                        ),
                      ),
                    ],
                  )
                : ListView.builder(
                    itemCount: filtered.length,
                    itemBuilder: (context, i) {
                      final m = filtered[i];
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        child: ListTile(
                          contentPadding:
                              const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          leading: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primaryContainer
                                  .withValues(alpha: 0.5),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              m.isYoutube ? Icons.play_circle : Icons.audiotrack,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                          ),
                          title:
                              Text(m.title, style: const TextStyle(fontWeight: FontWeight.bold)),
                          subtitle: Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Row(
                              children: [
                                Text(
                                  m.type.toUpperCase(),
                                  style: TextStyle(color: muted, fontSize: 12),
                                ),
                                const SizedBox(width: 8),
                                DifficultyChip(
                                  label: m.difficultyLabel,
                                  difficulty: m.difficulty,
                                ),
                              ],
                            ),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/media/${m.id}', extra: m),
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }
}
