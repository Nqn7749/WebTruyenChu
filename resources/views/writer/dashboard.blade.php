@extends('layouts.public')
@section('title', 'Tổng quan tác giả')
@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Tổng quan tác giả</h1>
    <p class="text-muted mb-0">Theo dõi hiệu quả các truyện bạn đã đăng.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Truyện</div><div class="fs-3 fw-bold">{{ number_format($totals['stories']) }}</div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Lượt xem</div><div class="fs-3 fw-bold">{{ number_format($totals['views']) }}</div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Chương</div><div class="fs-3 fw-bold">{{ number_format($totals['chapters']) }}</div></div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Bình luận hiển thị</div><div class="fs-3 fw-bold">{{ number_format($totals['comments']) }}</div></div></div></div>
    <div class="col-12 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Lịch sử đọc 30 ngày</div><div class="fs-3 fw-bold">{{ number_format($totals['readers_30_days']) }}</div></div></div></div>
</div>

@php($maxReads = max(1, $dailyActivity->max('reads')))
<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-1">Hoạt động đọc trong 30 ngày</h2>
        <p class="small text-muted mb-3">Số bản ghi lịch sử đọc trên các truyện của bạn theo ngày.</p>
        <div class="d-flex align-items-end gap-1" style="height: 150px;">
            @foreach ($dailyActivity as $item)
                <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ $item['date'] }}: {{ number_format($item['reads']) }} lượt ghi nhận">
                    <div class="w-100 bg-primary rounded-top" style="height: {{ max(3, ($item['reads'] / $maxReads) * 100) }}%;"></div>
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-between small text-muted mt-2">
            <span>{{ $dailyActivity->first()['label'] }}</span>
            <span>{{ $dailyActivity->last()['label'] }}</span>
        </div>
    </div>
</section>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Hiệu quả từng truyện</h2>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-sm btn-outline-primary">Quản lý truyện</a>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Truyện</th><th>Lượt xem</th><th>Chương</th><th>Bình luận</th><th>Lịch sử đọc</th><th>30 ngày</th></tr></thead>
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
