@extends('layouts.public')
@section('title', 'Truyện của tôi')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Truyện của tôi</h1><p class="text-muted mb-0">Quản lý truyện và các chương bạn đã gửi.</p></div>
    <a href="{{ route('writer.stories.create') }}" class="btn btn-primary">+ Đăng truyện</a>
</div>
<div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>Tên truyện</th><th>Số chương</th><th>Trạng thái duyệt</th><th>Thao tác</th></tr></thead>
<tbody>
@forelse ($stories as $story)
<tr>
    <td><strong>{{ $story->title }}</strong><div class="small text-muted">{{ $story->author_name ?: auth()->user()->name }}</div></td>
    <td>{{ $story->chapters_count }}</td>
    <td>@if ($story->status_publish)<span class="badge bg-success">Đã duyệt / công khai</span>@else<span class="badge bg-warning text-dark">Chờ duyệt / bản nháp</span>@endif</td>
    <td class="text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('writer.stories.chapters.index', $story) }}">Quản lý chương</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('writer.stories.edit', $story) }}">Sửa</a>
        <form class="d-inline" method="POST" action="{{ route('writer.stories.destroy', $story) }}" onsubmit="return confirm('Xóa truyện này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form>
    </td>
</tr>
@empty
<tr><td colspan="4" class="text-center text-muted py-4">Bạn chưa đăng truyện nào.</td></tr>
@endforelse
</tbody></table></div>
{{ $stories->links() }}
@endsection
