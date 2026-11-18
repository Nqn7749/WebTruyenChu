@extends('layouts.public')
@section('title', 'Quản lý bình luận')
@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Bình luận trên truyện của tôi</h1>
    <p class="text-muted mb-0">Bạn có thể ẩn hoặc hiện bình luận trên những truyện do mình sở hữu.</p>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Nội dung</th><th>Truyện / chương</th><th>Người gửi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
<tbody>
@forelse ($comments as $comment)
<tr>
    <td class="text-break" style="min-width:240px">{{ $comment->content }}<div class="small text-muted">{{ $comment->created_at->format('d/m/Y H:i') }}</div></td>
    <td>{{ $comment->story?->title ?? 'Truyện đã xóa' }}<div class="small text-muted">{{ $comment->chapter ? 'Chương '.$comment->chapter->chapter_number : 'Bình luận truyện' }}</div></td>
    <td>{{ $comment->user?->name ?? 'Thành viên đã xóa' }}</td>
    <td><span class="badge {{ $comment->is_hidden ? 'bg-secondary' : 'bg-success' }}">{{ $comment->is_hidden ? 'Đã ẩn' : 'Đang hiển thị' }}</span></td>
    <td><form method="POST" action="{{ route('writer.comments.toggle-hidden', $comment) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-{{ $comment->is_hidden ? 'success' : 'secondary' }}">{{ $comment->is_hidden ? 'Hiện lại' : 'Ẩn bình luận' }}</button></form></td>
</tr>
@empty
<tr><td colspan="5" class="text-center text-muted py-4">Chưa có bình luận nào.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $comments->links() }}
@endsection
