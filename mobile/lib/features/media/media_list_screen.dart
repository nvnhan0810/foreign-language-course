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

  Future<void> _openAddFromYouTube() async {
    final added = await showModalBottomSheet<MediaItem>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => const _AddYouTubeSheet(),
    );
    if (added == null || !mounted) return;
    await _load();
    if (!mounted) return;
    context.push('/media/${added.id}', extra: added);
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
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;

    if (allItems.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              'No media yet',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Add a YouTube video to start listening practice.',
              textAlign: TextAlign.center,
              style: TextStyle(color: muted),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _openAddFromYouTube,
              icon: const Icon(Icons.ondemand_video),
              label: const Text('Add from YouTube'),
            ),
          ],
        ),
      );
    }

    final filtered = _filteredItems;

    return Stack(
      children: [
        Column(
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
                              'No media at this difficulty.',
                              style: TextStyle(color: muted),
                            ),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.only(bottom: 88),
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
                              title: Text(
                                m.title,
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
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
        ),
        Positioned(
          right: 16,
          bottom: 16,
          child: FloatingActionButton.extended(
            onPressed: _openAddFromYouTube,
            icon: const Icon(Icons.ondemand_video),
            label: const Text('Add from YouTube'),
          ),
        ),
      ],
    );
  }
}

class _AddYouTubeSheet extends ConsumerStatefulWidget {
  const _AddYouTubeSheet();

  @override
  ConsumerState<_AddYouTubeSheet> createState() => _AddYouTubeSheetState();
}

class _AddYouTubeSheetState extends ConsumerState<_AddYouTubeSheet> {
  final _urlController = TextEditingController();
  final _titleController = TextEditingController();
  YouTubePreview? _preview;
  String _difficulty = 'intermediate';
  String _frequency = 'weekly';
  bool _fetching = false;
  bool _saving = false;
  String? _status;
  bool _statusIsError = false;

  @override
  void dispose() {
    _urlController.dispose();
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _fetch() async {
    final url = _urlController.text.trim();
    if (url.isEmpty) {
      setState(() {
        _status = 'Paste a YouTube URL first.';
        _statusIsError = true;
      });
      return;
    }

    setState(() {
      _fetching = true;
      _status = 'Fetching video info…';
      _statusIsError = false;
      _preview = null;
    });

    try {
      final preview = await ref.read(flcApiProvider).previewYouTube(url);
      if (!mounted) return;
      setState(() {
        _preview = preview;
        _titleController.text = preview.title;
        _status = preview.authorName != null
            ? 'Found · ${preview.authorName}'
            : 'Found. Edit the title if you want, then save.';
        _statusIsError = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _status = e.message;
        _statusIsError = true;
      });
    } finally {
      if (mounted) setState(() => _fetching = false);
    }
  }

  Future<void> _save() async {
    final preview = _preview;
    if (preview == null) {
      setState(() {
        _status = 'Fetch the video first.';
        _statusIsError = true;
      });
      return;
    }

    final title = _titleController.text.trim();
    if (title.isEmpty) {
      setState(() {
        _status = 'Title is required.';
        _statusIsError = true;
      });
      return;
    }

    setState(() {
      _saving = true;
      _status = 'Saving…';
      _statusIsError = false;
    });

    try {
      final item = await ref.read(flcApiProvider).createListeningMedia(
            title: title,
            url: preview.url,
            difficulty: _difficulty,
            frequency: _frequency,
          );
      if (!mounted) return;
      Navigator.of(context).pop(item);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _status = e.message;
        _statusIsError = true;
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    final errorColor = Theme.of(context).colorScheme.error;

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 12, 20, 20 + bottom),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: muted.withValues(alpha: 0.4),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            Text(
              'Add from YouTube',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 6),
            Text(
              'Paste a watch, youtu.be, or Shorts link.',
              style: TextStyle(color: muted, fontSize: 13),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _urlController,
              decoration: const InputDecoration(
                labelText: 'YouTube URL',
                hintText: 'https://www.youtube.com/watch?v=...',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
              textInputAction: TextInputAction.go,
              onSubmitted: (_) => _fetching || _saving ? null : _fetch(),
              enabled: !_fetching && !_saving,
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _fetching || _saving ? null : _fetch,
              icon: _fetching
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.search),
              label: Text(_fetching ? 'Fetching…' : 'Fetch'),
            ),
            if (_status != null) ...[
              const SizedBox(height: 10),
              Text(
                _status!,
                style: TextStyle(
                  color: _statusIsError ? errorColor : muted,
                  fontSize: 13,
                ),
              ),
            ],
            if (_preview != null) ...[
              const SizedBox(height: 16),
              if (_preview!.thumbnailUrl.isNotEmpty)
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Image.network(
                      _preview!.thumbnailUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => ColoredBox(
                        color: Theme.of(context).colorScheme.surfaceContainerHighest,
                        child: const Center(child: Icon(Icons.videocam_off)),
                      ),
                    ),
                  ),
                ),
              const SizedBox(height: 14),
              TextField(
                controller: _titleController,
                decoration: const InputDecoration(
                  labelText: 'Title',
                  border: OutlineInputBorder(),
                ),
                enabled: !_saving,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      // ignore: deprecated_member_use
                      value: _difficulty,
                      decoration: const InputDecoration(
                        labelText: 'Difficulty',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'beginner', child: Text('Beginner')),
                        DropdownMenuItem(value: 'intermediate', child: Text('Intermediate')),
                        DropdownMenuItem(value: 'advanced', child: Text('Advanced')),
                      ],
                      onChanged: _saving
                          ? null
                          : (v) {
                              if (v != null) setState(() => _difficulty = v);
                            },
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      // ignore: deprecated_member_use
                      value: _frequency,
                      decoration: const InputDecoration(
                        labelText: 'Remind',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'daily', child: Text('Daily')),
                        DropdownMenuItem(value: 'weekly', child: Text('Weekly')),
                        DropdownMenuItem(value: 'monthly', child: Text('Monthly')),
                      ],
                      onChanged: _saving
                          ? null
                          : (v) {
                              if (v != null) setState(() => _frequency = v);
                            },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Save'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
