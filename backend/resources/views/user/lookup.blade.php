@extends('user.layout')

@section('title', 'Tra từ — FLC')
@section('heading', 'Tra từ')

@section('content')
    <p class="muted" style="margin-top:0;font-weight:600">Tra từ Anh–Anh (giống app)</p>

    <form action="{{ route('user.home.lookup.search') }}" method="POST">
        @csrf
        <div class="form-group">
            <input
                type="text"
                name="word"
                class="form-control"
                placeholder="Nhập từ hoặc dán vào đây..."
                value="{{ old('word', $word) }}"
                autofocus
            >
        </div>
        <button type="submit" class="btn btn-block">Tra từ</button>
    </form>

    @if ($result)
        <div class="card" style="margin-top:20px">
            <p class="card-title">{{ $result['word'] ?? '' }}</p>
            @if (!empty($result['phonetic']))
                <p class="card-subtitle" style="font-style:italic">{{ $result['phonetic'] }}</p>
            @endif

            @foreach ($result['meanings'] ?? [] as $meaning)
                <div class="meaning-block">
                    @if (!empty($meaning['partOfSpeech']))
                        <span class="pos-tag">{{ $meaning['partOfSpeech'] }}</span>
                    @endif
                    <p style="margin:4px 0">{{ $meaning['definition'] ?? '' }}</p>
                    @if (!empty($meaning['example']))
                        <p class="muted" style="font-style:italic;margin:4px 0">"{{ $meaning['example'] }}"</p>
                    @endif
                </div>
            @endforeach
        </div>

        @unless ($saved)
            <form action="{{ route('user.home.lookup.save') }}" method="POST" style="margin-top:12px">
                @csrf
                <input type="hidden" name="word" value="{{ $result['word'] ?? '' }}">
                <input type="hidden" name="phonetic" value="{{ $result['phonetic'] ?? '' }}">
                @foreach ($result['meanings'] ?? [] as $i => $meaning)
                    @foreach ($meaning as $key => $value)
                        <input type="hidden" name="meanings[{{ $i }}][{{ $key }}]" value="{{ $value }}">
                    @endforeach
                @endforeach
                <button type="submit" class="btn btn-secondary btn-block">Lưu từ</button>
            </form>
        @else
            <p class="muted" style="text-align:center;margin-top:12px">Đã lưu từ</p>
        @endunless
    @endif
@endsection
