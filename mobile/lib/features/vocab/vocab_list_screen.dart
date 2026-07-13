import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/dictionary_card.dart';
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
  String _query = '';
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
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

  List<Vocabulary> get _filteredItems {
    final items = _items ?? [];
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return items;
    return items.where((v) {
      final def = v.meanings.isNotEmpty ? v.meanings.first.definition : '';
      final phonetic = v.phonetic ?? '';
      return '${v.word} $def $phonetic'.toLowerCase().contains(q);
    }).toList();
  }

  Future<void> _delete(Vocabulary v) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete word?'),
        content: Text('Remove "${v.word}" from your list?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(flcApiProvider).deleteVocabulary(v.id);
    _load();
  }

  Widget _searchField() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: TextField(
        controller: _searchController,
        decoration: InputDecoration(
          hintText: 'Search saved words...',
          prefixIcon: const Icon(Icons.search),
          filled: true,
          fillColor: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          contentPadding: const EdgeInsets.symmetric(vertical: 16),
          suffixIcon: _query.isEmpty
              ? null
              : IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _searchController.clear();
                    setState(() => _query = '');
                  },
                ),
        ),
        textInputAction: TextInputAction.search,
        onChanged: (value) => setState(() => _query = value),
      ),
    );
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
            TextButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }
    final allItems = _items ?? [];
    if (allItems.isEmpty) {
      return const Center(child: Text('No words yet. Look up a word and tap Save.'));
    }

    final items = _filteredItems;
    return Column(
      children: [
        _searchField(),
        Expanded(
          child: items.isEmpty
              ? const Center(child: Text('No matching words.'))
              : RefreshIndicator(
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
                            trailing: v.phonetic != null
                                ? Text(v.phonetic!, style: const TextStyle(fontStyle: FontStyle.italic))
                                : null,
                            onTap: () => showModalBottomSheet(
                              context: context,
                              isScrollControlled: true,
                              backgroundColor: Colors.transparent,
                              builder: (ctx) => DraggableScrollableSheet(
                                expand: false,
                                initialChildSize: 0.6,
                                builder: (_, scroll) => Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(ctx).colorScheme.surface,
                                    borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
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
                                      DictionaryCard.fromVocabulary(v),
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
                ),
        ),
      ],
    );
  }
}
