@extends('user.layout')

@section('title', $assessment->title . ' — FLC')
@section('heading', $assessment->title)

@php
    $backUrl = $assessment->mediaItem
        ? route('user.home.media.show', $assessment->mediaItem)
        : route('user.home.media');
@endphp

@section('hide_nav', true)
@section('back_url', $backUrl)

@if (!$result && $assessment->mediaItem)
    @section('below_header')
        @include('user.partials.listening-media-player', ['media' => $assessment->mediaItem])
    @endsection
@endif

@section('content')
    @if ($result)
        <div class="card result-card">
            <p class="muted" style="margin:0 0 8px">Result</p>
            <p class="result-score">{{ $result['score'] }}/{{ $result['total'] }}</p>
            <p style="margin:8px 0 0;font-weight:600">{{ $result['percentage'] }}%</p>
        </div>
        <a href="{{ $backUrl }}" class="btn btn-block" style="margin-top:16px">Back to media</a>
    @else
        <form action="{{ route('user.listening.submit', $assessment) }}" method="POST" class="flc-form-submit">
            @csrf
            @foreach ($questions as $index => $q)
                <div class="card question-card">
                    <p class="question-label">Question {{ $index + 1 }}</p>
                    <p class="question-prompt">{{ $q->prompt }}</p>

                    @if (is_array($q->options) && count($q->options) > 0)
                        <div class="choice-group">
                            @foreach ($q->options as $option)
                                <label class="choice-card">
                                    <input type="radio" name="answers[{{ $q->id }}]" value="{{ $option }}" required>
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <input
                            type="text"
                            name="answers[{{ $q->id }}]"
                            class="form-control"
                            placeholder="Enter your answer..."
                            required
                            autocomplete="off"
                        >
                    @endif
                </div>
            @endforeach

            <button type="submit" class="btn btn-block btn-submit-sticky">Submit</button>
        </form>
    @endif
@endsection
