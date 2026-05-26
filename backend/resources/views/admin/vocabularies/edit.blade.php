@extends('admin.layout')

@section('title', 'Sửa từ')
@section('heading', 'Sửa từ: '.$vocabulary->word)

@section('content')
<div class="card">
    <p class="muted">User: {{ $vocabulary->user?->email }}</p>
    <form method="POST" action="{{ route('admin.vocabularies.update', $vocabulary) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="word">Từ</label>
            <input type="text" name="word" id="word" value="{{ old('word', $vocabulary->word) }}" required>
        </div>

        <div class="form-group">
            <label for="phonetic">Phiên âm</label>
            <input type="text" name="phonetic" id="phonetic" value="{{ old('phonetic', $vocabulary->phonetic) }}">
        </div>

        <div class="form-group">
            <label for="meanings_json">Meanings (JSON)</label>
            <textarea name="meanings_json" id="meanings_json" rows="12" required>{{ old('meanings_json', json_encode($vocabulary->meanings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Lưu</button>
            <a href="{{ route('admin.vocabularies.index') }}" class="btn btn-secondary">Quay lại</a>
        </div>
    </form>
</div>
@endsection
