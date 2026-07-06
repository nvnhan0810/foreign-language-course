import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/pronunciation_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class VocabListScreen extends ConsumerStatefulWidget {
  const VocabListScreen({super.key});

  @override
  ConsumerState<VocabListScreen> createState() => _VocabListScreenState();
}

class _VocabListScreenState extends ConsumerState<VocabListScreen> {
  List<Vocabulary>? _items;
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
      final items = await ref.read(flcApiProvider).listVocabularies();
      setState(() => _items = items);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _delete(Vocabulary v) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xóa từ?'),
        content: Text('Xóa "${v.word}" khỏi danh sách?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hủy')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Xóa')),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(flcApiProvider).deleteVocabulary(v.id);
    _load();
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
      return const Center(child: Text('Chưa có từ nào. Tra từ và bấm Lưu.'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, i) {
          final v = items[i];
          final def = v.meanings.isNotEmpty ? v.meanings.first.definition : '';
          return Card(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Dismissible(
              key: ValueKey(v.id),
              direction: DismissDirection.endToStart,
              confirmDismiss: (_) async {
                await _delete(v);
                return false;
              },
              background: Container(
                decoration: BoxDecoration(
                  color: Colors.red.shade400,
                  borderRadius: BorderRadius.circular(16),
                ),
                alignment: Alignment.centerRight,
                padding: const EdgeInsets.only(right: 20),
                child: const Icon(Icons.delete, color: Colors.white),
              ),
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                title: Row(
                  children: [
                    Expanded(
                      child: Text(v.word, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                    ),
                    PronunciationButton(word: v.word, iconSize: 20),
                  ],
                ),
                subtitle: Padding(
                  padding: const EdgeInsets.only(top: 8.0),
                  child: Text(
                    def,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: Colors.grey.shade700),
                  ),
                ),
                trailing: v.phonetic != null ? Text(v.phonetic!, style: const TextStyle(fontStyle: FontStyle.italic)) : null,
                onTap: () => showModalBottomSheet(
                  context: context,
                  isScrollControlled: true,
                  backgroundColor: Colors.transparent,
                  builder: (ctx) => DraggableScrollableSheet(
                    expand: false,
                    initialChildSize: 0.6,
                    builder: (_, scroll) => Container(
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                      ),
                      child: ListView(
                        controller: scroll,
                        padding: const EdgeInsets.all(24),
                        children: [
                          Center(
                            child: Container(
                              width: 40,
                              height: 4,
                              margin: const EdgeInsets.only(bottom: 24),
                              decoration: BoxDecoration(
                                color: Colors.grey.shade300,
                                borderRadius: BorderRadius.circular(2),
                              ),
                            ),
                          ),
                          Text(v.word, style: Theme.of(ctx).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
                          PronunciationButton(word: v.word),
                          if (v.phonetic != null) 
                            Padding(
                              padding: const EdgeInsets.only(top: 4.0),
                              child: Text(v.phonetic!, style: TextStyle(color: Colors.grey.shade600, fontSize: 16, fontStyle: FontStyle.italic)),
                            ),
                          const SizedBox(height: 16),
                          const Divider(),
                          const SizedBox(height: 8),
                          ...v.meanings.map(
                            (m) => Padding(
                              padding: const EdgeInsets.only(bottom: 16.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (m.partOfSpeech != null)
                                    Container(
                                      margin: const EdgeInsets.only(bottom: 8),
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: Theme.of(ctx).colorScheme.primaryContainer.withValues(alpha: 0.5),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Text(
                                        m.partOfSpeech!,
                                        style: TextStyle(
                                          color: Theme.of(ctx).colorScheme.primary,
                                          fontStyle: FontStyle.italic,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  Text(m.definition, style: const TextStyle(fontSize: 16)),
                                  if (m.example != null)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 8.0),
                                      child: Text(
                                        '"${m.example}"',
                                        style: TextStyle(color: Colors.grey.shade600, fontStyle: FontStyle.italic),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
