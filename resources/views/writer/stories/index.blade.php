@extends('layouts.public')
@section('title', 'Truyện của tôi')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Truyện của tôi</h1>
        <p class="text-muted mb-0">Truyện được công khai ngay sau khi đăng. Hãy thêm chương đầu tiên trong thời hạn quy định.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('writer.dashboard') }}" class="btn btn-outline-primary">Tổng quan</a>
        <a href="{{ route('writer.comments.index') }}" class="btn btn-outline-secondary">Bình luận</a>
        <a href="{{ route('writer.stories.import') }}" class="btn btn-outline-primary">Đăng từ tệp</a>
        <a href="{{ route('writer.stories.create') }}" class="btn btn-primary">+ Đăng truyện</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Tên truyện</th><th>Số chương</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
<tbody>
@forelse ($stories as $story)
<tr>
    <td>
        <strong>{{ $story->title }}</strong>
        <div class="small text-muted">{{ $story->author_name ?: auth()->user()->name }}</div>
        @if ($story->trashed())
            <div class="alert alert-danger mt-2 mb-0 py-2">
                <strong>Truyện đã bị xóa.</strong>
                Lý do: {{ $story->deletion_reason ?: 'Không có thông tin lý do.' }}
            </div>
        @elseif ($story->chapters_count === 0 && $story->created_at->lte(now()->subDays(7)))
            <div class="alert alert-warning mt-2 mb-0 py-2">
                Truyện đã đăng {{ $story->created_at->diffInDays(now()) }} ngày nhưng chưa có chương.
                Vui lòng thêm chương trước khi đủ 30 ngày; nếu không, hệ thống sẽ tự động xóa truyện.
            </div>
        @endif
    </td>
    <td>{{ $story->chapters_count }}</td>
    <td>
        @if ($story->trashed())
            <span class="badge bg-danger">Đã xóa</span>
        @elseif ($story->status_publish)
            <span class="badge bg-success">Đang công khai</span>
        @else
            <span class="badge bg-secondary">Chưa công khai</span>
        @endif
    </td>
    <td class="text-nowrap">
        @unless ($story->trashed())
            <a class="btn btn-sm btn-outline-primary" href="{{ route('writer.stories.chapters.index', $story) }}">Quản lý chương</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('writer.stories.edit', $story) }}">Sửa</a>
            <form class="d-inline" method="POST" action="{{ route('writer.stories.destroy', $story) }}"
                  onsubmit="return confirm('Bạn có chắc muốn xóa truyện này?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Xóa</button>
            </form>
        @else
            <span class="text-muted small">Không thể chỉnh sửa</span>
        @endunless
    </td>
</tr>
@empty
<tr><td colspan="4" class="text-center text-muted py-4">Bạn chưa đăng truyện nào.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $stories->links() }}
@endsection
