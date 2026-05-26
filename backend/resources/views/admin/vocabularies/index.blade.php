@extends('admin.layout')

@section('title', 'Từ vựng')
@section('heading', 'Từ vựng')

@section('content')
<form class="search-bar" method="GET">
    <input type="search" name="q" value="{{ $search }}" placeholder="Tìm từ...">
    <button type="submit" class="btn">Tìm</button>
</form>

<table>
    <thead>
        <tr>
            <th>Từ</th>
            <th>User</th>
            <th>Quiz</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($vocabularies as $v)
            <tr>
                <td><strong>{{ $v->word }}</strong></td>
                <td>{{ $v->user?->email }}</td>
                <td>{{ $v->times_quizzed }}</td>
                <td>
                    <a href="{{ route('admin.vocabularies.edit', $v) }}" class="btn btn-sm">Sửa</a>
                    <form action="{{ route('admin.vocabularies.destroy', $v) }}" method="POST" class="inline-form" onsubmit="return confirm('Xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $vocabularies->links() }}
@endsection
