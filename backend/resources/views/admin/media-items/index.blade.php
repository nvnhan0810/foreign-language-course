@extends('admin.layout')

@section('title', 'Media')
@section('heading', 'Media / Link nghe')

@section('content')
<form class="search-bar" method="GET">
    <input type="search" name="q" value="{{ $search }}" placeholder="Tìm tiêu đề hoặc URL...">
    <button type="submit" class="btn">Tìm</button>
</form>

<table>
    <thead>
        <tr>
            <th>Tiêu đề</th>
            <th>User</th>
            <th>Loại</th>
            <th>Tần suất</th>
            <th>Active</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($mediaItems as $m)
            <tr>
                <td>{{ $m->title }}</td>
                <td>{{ $m->user?->email }}</td>
                <td>{{ $m->type }}</td>
                <td>{{ $m->frequency }}</td>
                <td>{{ $m->is_active ? '✓' : '—' }}</td>
                <td>
                    <a href="{{ route('admin.media-items.edit', $m) }}" class="btn btn-sm">Sửa</a>
                    <form action="{{ route('admin.media-items.destroy', $m) }}" method="POST" class="inline-form" onsubmit="return confirm('Xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $mediaItems->links() }}
@endsection
