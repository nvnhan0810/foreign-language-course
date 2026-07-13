@extends('admin.layout')

@section('title', $entry ? 'Sửa: '.$entry->word : 'Thêm từ')
@section('heading', $entry ? 'Sửa: '.$entry->word : 'Thêm từ vào My Dictionary')

@section('content')
<div class="card">
    @if ($entry)
        <p class="muted">
            save_count={{ $entry->save_count }} ·
            curated={{ $entry->is_curated ? 'yes' : 'no' }} ·
            source={{ $entry->source }}
        </p>
    @endif

    <form method="POST" action="{{ $entry ? route('admin.dictionary.update', $entry) : route('admin.dictionary.store') }}" id="dictionary-form">
        @csrf
        @if ($entry)
            @method('PUT')
        @endif

        <div class="form-group">
            <label for="word">Từ</label>
            <input type="text" name="word" id="word" value="{{ old('word', $entry->word ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="phonetic">Phiên âm</label>
            <input type="text" name="phonetic" id="phonetic" value="{{ old('phonetic', $entry->phonetic ?? '') }}">
        </div>

        <div class="form-group">
            <label for="audio_url">Audio URL</label>
            <input type="url" name="audio_url" id="audio_url" value="{{ old('audio_url', $entry->audio_url ?? '') }}">
        </div>

        <div class="form-group">
            <label for="entry_synonyms">Đồng nghĩa (cấp từ, cách nhau bởi dấu phẩy)</label>
            <input type="text" name="entry_synonyms" id="entry_synonyms" value="{{ old('entry_synonyms', $entrySynonymsText) }}">
        </div>

        <div class="form-group">
            <label for="entry_antonyms">Trái nghĩa (cấp từ, cách nhau bởi dấu phẩy)</label>
            <input type="text" name="entry_antonyms" id="entry_antonyms" value="{{ old('entry_antonyms', $entryAntonymsText) }}">
        </div>

        <h3 style="margin-top:24px">Nghĩa</h3>
        <p class="muted">Mỗi nghĩa: loại từ + định nghĩa + examples (mỗi dòng một câu) + đồng/trái nghĩa theo nghĩa.</p>

        <div id="meanings-list">
            @php
                $meanings = old('meanings', $formMeanings);
            @endphp
            @foreach ($meanings as $i => $meaning)
                <div class="card meaning-card" style="margin:12px 0;padding:14px;border:1px solid var(--border)">
                    <div class="form-group">
                        <label>Loại từ (POS)</label>
                        <input type="text" name="meanings[{{ $i }}][part_of_speech]" value="{{ $meaning['part_of_speech'] ?? '' }}" placeholder="noun, verb, adjective...">
                    </div>
                    <div class="form-group">
                        <label>Định nghĩa</label>
                        <textarea name="meanings[{{ $i }}][definition]" rows="2" required>{{ $meaning['definition'] ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Examples (mỗi dòng một câu)</label>
                        <textarea name="meanings[{{ $i }}][examples_text]" rows="3" placeholder="She is a bright student.">{{ $meaning['examples_text'] ?? '' }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Đồng nghĩa theo nghĩa</label>
                        <input type="text" name="meanings[{{ $i }}][synonyms_text]" value="{{ $meaning['synonyms_text'] ?? '' }}" placeholder="clever, smart">
                    </div>
                    <div class="form-group">
                        <label>Trái nghĩa theo nghĩa</label>
                        <input type="text" name="meanings[{{ $i }}][antonyms_text]" value="{{ $meaning['antonyms_text'] ?? '' }}" placeholder="dull, dim">
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" data-remove-meaning>Xóa nghĩa này</button>
                </div>
            @endforeach
        </div>

        <button type="button" class="btn btn-secondary" id="add-meaning">+ Thêm nghĩa</button>

        <div class="form-actions" style="margin-top:20px">
            <button type="submit" class="btn">Lưu (đánh dấu curated)</button>
            <a href="{{ route('admin.dictionary.index') }}" class="btn btn-secondary">Quay lại</a>
        </div>
    </form>
</div>

<template id="meaning-template">
    <div class="card meaning-card" style="margin:12px 0;padding:14px;border:1px solid var(--border)">
        <div class="form-group">
            <label>Loại từ (POS)</label>
            <input type="text" name="meanings[__INDEX__][part_of_speech]" placeholder="noun, verb, adjective...">
        </div>
        <div class="form-group">
            <label>Định nghĩa</label>
            <textarea name="meanings[__INDEX__][definition]" rows="2" required></textarea>
        </div>
        <div class="form-group">
            <label>Examples (mỗi dòng một câu)</label>
            <textarea name="meanings[__INDEX__][examples_text]" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Đồng nghĩa theo nghĩa</label>
            <input type="text" name="meanings[__INDEX__][synonyms_text]" placeholder="clever, smart">
        </div>
        <div class="form-group">
            <label>Trái nghĩa theo nghĩa</label>
            <input type="text" name="meanings[__INDEX__][antonyms_text]" placeholder="dull, dim">
        </div>
        <button type="button" class="btn btn-sm btn-danger" data-remove-meaning>Xóa nghĩa này</button>
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
