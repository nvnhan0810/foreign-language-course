import 'package:flc_mobile/core/api/api_client.dart';
import 'package:flc_mobile/core/providers/app_providers.dart';
import 'package:flc_mobile/core/utils/lookup_utils.dart';
import 'package:flc_mobile/models/flc_models.dart';
import 'package:flc_mobile/widgets/dictionary_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class LookupScreen extends ConsumerStatefulWidget {
  const LookupScreen({super.key});

  @override
  ConsumerState<LookupScreen> createState() => _LookupScreenState();
}

class _LookupScreenState extends ConsumerState<LookupScreen> {
  final _controller = TextEditingController();
  DictionaryResult? _result;
  bool _loading = false;
  String? _error;
  bool _saved = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _lookup() async {
    final text = _controller.text.trim();
    if (!isValidLookupInput(text)) {
      setState(() => _error = 'Nhập từ/câu tiếng Anh hợp lệ.');
      return;
    }
    final word = lookupTermFromText(text);
    setState(() {
      _loading = true;
      _error = null;
      _result = null;
      _saved = false;
    });
    try {
      final result = await ref.read(flcApiProvider).lookup(word);
      setState(() => _result = result);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (_result == null) return;
    setState(() => _loading = true);
    try {
      await ref.read(flcApiProvider).saveVocabulary(
            word: _result!.word,
            phonetic: _result!.phonetic,
            meanings: _result!.meanings,
          );
      setState(() => _saved = true);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã lưu từ')),
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Tra từ Anh–Anh (giống extension)',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _controller,
          decoration: InputDecoration(
            hintText: 'Nhập từ hoặc dán vào đây...',
            prefixIcon: const Icon(Icons.search),
            filled: true,
            fillColor: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide.none,
            ),
            contentPadding: const EdgeInsets.symmetric(vertical: 16),
          ),
          textInputAction: TextInputAction.search,
          onSubmitted: (_) => _lookup(),
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 50,
          child: FilledButton(
            onPressed: _loading ? null : _lookup,
            child: _loading 
                ? const SizedBox(
                    width: 24, 
                    height: 24, 
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)
                  )
                : const Text('Tra từ', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ],
        if (_result != null) ...[
          const SizedBox(height: 16),
          DictionaryCard(result: _result!),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: _saved || _loading ? null : _save,
            child: Text(_saved ? 'Đã lưu' : 'Lưu từ'),
          ),
        ],
      ],
    );
  }
}
