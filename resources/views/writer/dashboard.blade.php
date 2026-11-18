@extends('layouts.public')
@section('title', 'Tổng quan tác giả')
@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Tổng quan tác giả</h1>
    <p class="text-muted mb-0">Theo dõi hiệu quả các truyện bạn đã đăng.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Truyện</div><div class="fs-3 fw-bold">{{ number_format($totals['stories']) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Lượt xem</div><div class="fs-3 fw-bold">{{ number_format($totals['views']) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Chương</div><div class="fs-3 fw-bold">{{ number_format($totals['chapters']) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Bình luận hiển thị</div><div class="fs-3 fw-bold">{{ number_format($totals['comments']) }}</div></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Hiệu quả từng truyện</h2>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-sm btn-outline-primary">Quản lý truyện</a>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Truyện</th><th>Lượt xem</th><th>Chương</th><th>Bình luận</th><th>Người đọc ghi nhận</th><th>30 ngày</th></tr></thead>
<tbody>
@forelse ($stories as $story)
<tr>
    <td><a href="{{ route('writer.stories.edit', $story) }}">{{ $story->title }}</a><div class="small text-muted">{{ $story->status_publish ? 'Đang công khai' : 'Chưa công khai' }}</div></td>
    <td>{{ number_format($story->views) }}</td>
    <td>{{ number_format($story->chapters_count) }}</td>
    <td>{{ number_format($story->visible_comments_count) }}</td>
    <td>{{ number_format($story->reading_histories_count) }}</td>
    <td>{{ number_format($story->readers_last_30_days_count) }}</td>
</tr>
@empty
<tr><td colspan="6" class="text-center text-muted py-4">Bạn chưa có truyện nào.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $stories->links() }}
@endsection
