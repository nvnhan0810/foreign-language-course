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

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final items = await ref.read(flcApiProvider).listMedia();
      setState(() => _items = items);
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _load,
            child: (_items ?? []).isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 80),
                      Center(child: Text('Chưa có media. Thêm từ trang admin.')),
                    ],
                  )
                : ListView.builder(
                    itemCount: _items!.length,
                    itemBuilder: (context, i) {
                      final m = _items![i];
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
                            padding: const EdgeInsets.only(top: 4.0),
                            child: Text(m.type.toUpperCase(), style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
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
