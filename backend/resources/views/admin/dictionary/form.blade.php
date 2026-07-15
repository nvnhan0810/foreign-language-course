@extends('admin.layout')

@section('title', $entry ? 'Sửa: '.$entry->word : 'Thêm từ')
@section('heading', $entry ? 'Sửa: '.$entry->word : 'Thêm từ / cụm từ')

@section('content')
<div class="card dictionary-form-card">
    @if ($entry)
        <p class="muted" style="margin-top:0">
            save_count={{ $entry->save_count }} ·
            curated={{ $entry->is_curated ? 'yes' : 'no' }} ·
            source={{ $entry->source }}
        </p>
    @else
        <p class="muted" style="margin-top:0">Curate từ hoặc cụm từ dùng chung cho lookup.</p>
    @endif

    <form method="POST" action="{{ $entry ? route('admin.dictionary.update', $entry) : route('admin.dictionary.store') }}" id="dictionary-form">
        @csrf
        @if ($entry)
            @method('PUT')
        @endif

        <div class="form-grid form-grid-3">
            <div class="form-group">
                <label for="word">Từ / cụm từ</label>
                <input type="text" name="word" id="word" value="{{ old('word', $entry->word ?? '') }}" placeholder="happy, get along with..." required>
            </div>
            <div class="form-group">
                <label for="phonetic">Phiên âm</label>
                <input type="text" name="phonetic" id="phonetic" value="{{ old('phonetic', $entry->phonetic ?? '') }}" placeholder="/ˈhæpi/">
            </div>
            <div class="form-group">
                <label for="audio_url">Audio URL</label>
                <input type="url" name="audio_url" id="audio_url" value="{{ old('audio_url', $entry->audio_url ?? '') }}">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="entry_synonyms">Đồng nghĩa (cấp từ)</label>
                <input type="text" name="entry_synonyms" id="entry_synonyms" value="{{ old('entry_synonyms', $entrySynonymsText) }}" placeholder="joyous, glad — cách nhau bởi dấu phẩy">
            </div>
            <div class="form-group">
                <label for="entry_antonyms">Trái nghĩa (cấp từ)</label>
                <input type="text" name="entry_antonyms" id="entry_antonyms" value="{{ old('entry_antonyms', $entryAntonymsText) }}" placeholder="sad, unhappy — cách nhau bởi dấu phẩy">
            </div>
        </div>

        <div class="meanings-header">
            <h3>Nghĩa</h3>
            <button type="button" class="btn btn-secondary btn-sm" id="add-meaning">+ Thêm nghĩa</button>
        </div>
        <p class="muted">Examples: mỗi dòng một câu. Đồng/trái nghĩa: cách nhau bởi dấu phẩy.</p>

        <div id="meanings-list">
            @php
                $meanings = old('meanings', $formMeanings);
            @endphp
            @foreach ($meanings as $i => $meaning)
                <div class="meaning-card">
                    <div class="meaning-card-top">
                        <span class="muted">Nghĩa {{ $i + 1 }}</span>
                        <button type="button" class="btn btn-sm btn-danger" data-remove-meaning>Xóa</button>
                    </div>
                    <div class="form-grid meaning-pos-def">
                        <div class="form-group">
                            <label>Loại từ (POS)</label>
                            <input type="text" name="meanings[{{ $i }}][part_of_speech]" value="{{ $meaning['part_of_speech'] ?? '' }}" placeholder="noun, verb, phrase...">
                        </div>
                        <div class="form-group form-span-grow">
                            <label>Định nghĩa</label>
                            <textarea name="meanings[{{ $i }}][definition]" rows="2" required>{{ $meaning['definition'] ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Examples</label>
                        <textarea name="meanings[{{ $i }}][examples_text]" rows="2" placeholder="They get along with each other well.">{{ $meaning['examples_text'] ?? '' }}</textarea>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Đồng nghĩa</label>
                            <input type="text" name="meanings[{{ $i }}][synonyms_text]" value="{{ $meaning['synonyms_text'] ?? '' }}" placeholder="clever, smart">
                        </div>
                        <div class="form-group">
                            <label>Trái nghĩa</label>
                            <input type="text" name="meanings[{{ $i }}][antonyms_text]" value="{{ $meaning['antonyms_text'] ?? '' }}" placeholder="dull, dim">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Lưu (đánh dấu curated)</button>
            <a href="{{ route('admin.dictionary.index') }}" class="btn btn-secondary">Quay lại</a>
        </div>
    </form>
</div>

<template id="meaning-template">
    <div class="meaning-card">
        <div class="meaning-card-top">
            <span class="muted">Nghĩa mới</span>
            <button type="button" class="btn btn-sm btn-danger" data-remove-meaning>Xóa</button>
        </div>
        <div class="form-grid meaning-pos-def">
            <div class="form-group">
                <label>Loại từ (POS)</label>
                <input type="text" name="meanings[__INDEX__][part_of_speech]" placeholder="noun, verb, phrase...">
            </div>
            <div class="form-group form-span-grow">
                <label>Định nghĩa</label>
                <textarea name="meanings[__INDEX__][definition]" rows="2" required></textarea>
            </div>
        </div>
        <div class="form-group">
            <label>Examples</label>
            <textarea name="meanings[__INDEX__][examples_text]" rows="2"></textarea>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Đồng nghĩa</label>
                <input type="text" name="meanings[__INDEX__][synonyms_text]" placeholder="clever, smart">
            </div>
            <div class="form-group">
                <label>Trái nghĩa</label>
                <input type="text" name="meanings[__INDEX__][antonyms_text]" placeholder="dull, dim">
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    var list = document.getElementById('meanings-list');
    var template = document.getElementById('meaning-template');
    var addBtn = document.getElementById('add-meaning');

    function nextIndex() {
        return list.querySelectorAll('.meaning-card').length;
    }

    addBtn.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
        list.insertAdjacentHTML('beforeend', html);
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-meaning]');
        if (!btn) return;
        var cards = list.querySelectorAll('.meaning-card');
        if (cards.length <= 1) {
            alert('Cần ít nhất một nghĩa.');
            return;
        }
        btn.closest('.meaning-card').remove();
    });
})();
</script>
@endsection
