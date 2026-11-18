@extends('layouts.admin')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Tổng quan hệ thống</h1>
    <p class="text-muted mb-0">Số liệu vận hành và hoạt động trong 30 ngày gần nhất.</p>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Tổng lượt xem', 'value' => $viewCount, 'note' => 'Trên truyện đang công khai'],
        ['label' => 'Truyện công khai', 'value' => $publishedStoryCount, 'note' => $storyCount.' truyện trong hệ thống'],
        ['label' => 'Thành viên hoạt động', 'value' => $activeUserCount, 'note' => $newUserCount.' thành viên mới trong 30 ngày'],
        ['label' => 'Chương truyện', 'value' => $chapterCount, 'note' => $commentCount.' bình luận đang hiển thị'],
    ] as $stat)
        <div class="col-6 col-xl-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="small text-muted">{{ $stat['label'] }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($stat['value']) }}</div>
                    <div class="small text-muted">{{ $stat['note'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@php
    $maxReads = max(1, $dailyActivity->max('reads'));
    $maxMembers = max(1, $dailyActivity->max('members'));
@endphp
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <section class="card h-100 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Hoạt động đọc trong 30 ngày</h2>
                <p class="small text-muted mb-3">Số bản ghi lịch sử đọc được cập nhật theo ngày, không phải tổng lượt xem ẩn danh.</p>
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
    </div>
    <div class="col-12 col-xl-4">
        <section class="card h-100 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Thành viên mới</h2>
                <p class="small text-muted mb-3">Số tài khoản đăng ký mỗi ngày.</p>
                <div class="d-flex align-items-end gap-1" style="height: 150px;">
                    @foreach ($dailyActivity as $item)
                        <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ $item['date'] }}: {{ number_format($item['members']) }} thành viên">
                            <div class="w-100 bg-success rounded-top" style="height: {{ max(3, ($item['members'] / $maxMembers) * 100) }}%;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2">
                    <span>{{ $dailyActivity->first()['label'] }}</span>
                    <span>{{ $dailyActivity->last()['label'] }}</span>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <section class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Truyện có lượt xem cao</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Truyện</th><th>Tác giả</th><th class="text-end">Lượt xem</th></tr></thead>
                        <tbody>
                        @forelse ($topStories as $story)
                            <tr>
                                <td>{{ $story->title }}</td>
                                <td>{{ $story->user?->name ?? 'Không rõ' }}</td>
                                <td class="text-end">{{ number_format($story->views) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu truyện.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Tác giả nổi bật</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Tác giả</th><th class="text-end">Truyện</th><th class="text-end">Lượt xem</th></tr></thead>
                        <tbody>
                        @forelse ($topAuthors as $author)
                            <tr>
                                <td>{{ $author->name }}</td>
                                <td class="text-end">{{ number_format($author->stories_count) }}</td>
                                <td class="text-end">{{ number_format($author->views_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu tác giả.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
