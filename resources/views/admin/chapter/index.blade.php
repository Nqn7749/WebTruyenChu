@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Chương truyện: {{ $story->title }}</h4>
    <a href="{{ route('admin.stories.chapters.create', $story) }}" class="btn btn-primary">+ Thêm chương</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>#</th>
            <th>Tiêu đề</th>
            <th>Lượt xem</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($chapters as $chapter)
            <tr>
                <td>{{ $chapter->chapter_number }}</td>
                <td>{{ $chapter->title ?? '(Không tiêu đề)' }}</td>
                <td>{{ $chapter->views }}</td>
                <td>
                    <span class="badge {{ $chapter->status ? 'bg-success' : 'bg-secondary' }}">
                        {{ $chapter->status ? 'Đã đăng' : 'Nháp' }}
                    </span>
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.stories.chapters.edit', $chapter) }}" class="btn btn-sm btn-warning">Sửa</a>
                    <form action="{{ route('admin.stories.chapters.destroy', $chapter) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa chương này?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">Chưa có chương nào.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $chapters->links() }}
@endsection