@extends('user.layout')

@section('title', 'Learn — FLC')
@section('heading', 'Learn')

@section('content')
    @include('user.partials.word-chat', [
        'chatId' => 'word-chat',
        'prefill' => $prefill,
    ])
@endsection
