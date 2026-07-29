@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Quản lý truyện</h4>
    <a href="{{ route('admin.stories.create') }}" class="btn btn-primary">+ Thêm truyện</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Ảnh</th>
            <th>Tên truyện</th>
            <th>Tác giả</th>
            <th>Trạng thái</th>
            <th>Số chương</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($stories as $story)
            <tr>
                <td>
                    @if ($story->cover_image)
                        <img src="{{ Storage::url($story->cover_image) }}" width="50">
                    @endif
                </td>
                <td>{{ $story->title }}</td>
                <td>{{ $story->author_name ?? '—' }}</td>
                <td><span class="badge bg-secondary">{{ $story->status }}</span></td>
                <td>{{ $story->chapter_count }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-info">Chương</a>
                    <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-sm btn-warning">Sửa</a>
                    <form action="{{ route('admin.stories.destroy', $story) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa truyện này?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center">Chưa có truyện nào.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $stories->links() }}
@endsection