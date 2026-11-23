@extends('layouts.admin')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Tổng quan hệ thống</h1>
        <p class="text-muted mb-0 small">Số liệu vận hành và hoạt động trong 30 ngày gần nhất.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.story-import.index') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
            <i class="bi bi-cloud-arrow-down"></i> Nhập truyện
        </a>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1">
            <i class="bi bi-shield-exclamation"></i> Xem báo cáo
        </a>
    </div>
</div>

{{-- KPI Metrics Grid --}}
<div class="row g-3 mb-4">
    @php
        $metricConfigs = [
            ['label' => 'Tổng lượt xem', 'value' => $viewCount, 'note' => 'Trên truyện công khai', 'icon' => 'bi-eye', 'color' => '#0284c7', 'bg' => 'rgba(2, 132, 199, 0.1)'],
            ['label' => 'Truyện công khai', 'value' => $publishedStoryCount, 'note' => $storyCount.' truyện trong hệ thống', 'icon' => 'bi-book', 'color' => '#059669', 'bg' => 'rgba(5, 150, 105, 0.1)'],
            ['label' => 'Thành viên hoạt động', 'value' => $activeUserCount, 'note' => $newUserCount.' thành viên mới (30 ngày)', 'icon' => 'bi-people', 'color' => '#7c3aed', 'bg' => 'rgba(124, 58, 237, 0.1)'],
            ['label' => 'Chương truyện', 'value' => $chapterCount, 'note' => $commentCount.' bình luận hiển thị', 'icon' => 'bi-journal-richtext', 'color' => '#d97706', 'bg' => 'rgba(217, 119, 6, 0.1)'],
        ];
    @endphp

    @foreach ($metricConfigs as $stat)
        <div class="col-6 col-xl-3">
            <div class="metric-kpi-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="small text-muted fw-semibold">{{ $stat['label'] }}</span>
                    <div class="metric-icon-wrap flex-shrink-0" style="color: {{ $stat['color'] }}; background-color: {{ $stat['bg'] }};">
                        <i class="bi {{ $stat['icon'] }}"></i>
                    </div>
                </div>
                <div class="fs-2 fw-bold mb-1" style="letter-spacing: -0.03em;">{{ number_format($stat['value']) }}</div>
                <div class="small text-muted" style="font-size: .78rem;">{{ $stat['note'] }}</div>
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
        <section class="card h-100 shadow-sm border-0" style="border: 1px solid var(--admin-border) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h2 class="h6 fw-bold mb-0">Hoạt động đọc trong 30 ngày</h2>
                    <span class="badge bg-light text-muted border small">Lịch sử đọc thực</span>
                </div>
                <p class="small text-muted mb-4">Số bản ghi tiến độ đọc cập nhật theo từng ngày.</p>
                <div class="d-flex align-items-end gap-1" style="height: 160px;">
                    @foreach ($dailyActivity as $item)
                        <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ $item['date'] }}: {{ number_format($item['reads']) }} lượt ghi nhận">
                            <div class="w-100 rounded-top" style="height: {{ max(3, ($item['reads'] / $maxReads) * 100) }}%; background-color: var(--admin-accent); opacity: 0.85; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2 pt-2 border-top">
                    <span><i class="bi bi-calendar3 me-1"></i> {{ $dailyActivity->first()['label'] }}</span>
                    <span>{{ $dailyActivity->last()['label'] }} <i class="bi bi-calendar3 ms-1"></i></span>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="card h-100 shadow-sm border-0" style="border: 1px solid var(--admin-border) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h2 class="h6 fw-bold mb-0">Thành viên mới</h2>
                    <span class="badge bg-light text-muted border small">Đăng ký mới</span>
                </div>
                <p class="small text-muted mb-4">Số tài khoản đăng ký mỗi ngày.</p>
                <div class="d-flex align-items-end gap-1" style="height: 160px;">
                    @foreach ($dailyActivity as $item)
                        <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ $item['date'] }}: {{ number_format($item['members']) }} thành viên">
                            <div class="w-100 bg-success rounded-top" style="height: {{ max(3, ($item['members'] / $maxMembers) * 100) }}%; opacity: 0.85; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2 pt-2 border-top">
                    <span>{{ $dailyActivity->first()['label'] }}</span>
                    <span>{{ $dailyActivity->last()['label'] }}</span>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <section class="card shadow-sm h-100 border-0" style="border: 1px solid var(--admin-border) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Truyện có lượt xem cao</h2>
                    <a href="{{ route('admin.stories.index') }}" class="small text-decoration-none">Xem tất cả &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>TRUYỆN</th>
                                <th>NGƯỜI ĐĂNG</th>
                                <th class="text-end">LƯỢT XEM</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($topStories as $story)
                            <tr @if ($loop->index >= 5) class="d-none" data-more="stories" @endif>
                                <td>
                                    <span class="fw-semibold">{{ $story->title }}</span>
                                </td>
                                <td><span class="small text-muted">{{ $story->user?->name ?? 'Không rõ' }}</span></td>
                                <td class="text-end fw-semibold">{{ number_format($story->views) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu truyện.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($topStories->count() > 5)
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-more-toggle="stories"
                                data-more-label="Xem thêm ({{ $topStories->count() - 5 }})" aria-expanded="false">Xem thêm ({{ $topStories->count() - 5 }})</button>
                    </div>
                @endif
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="card shadow-sm h-100 border-0" style="border: 1px solid var(--admin-border) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0">Tác giả nổi bật</h2>
                    <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">Xem thành viên &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>TÁC GIẢ</th>
                                <th class="text-end">TRUYỆN</th>
                                <th class="text-end">LƯỢT XEM</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($topAuthors as $author)
                            <tr @if ($loop->index >= 5) class="d-none" data-more="authors" @endif>
                                <td><span class="fw-semibold">{{ $author->name }}</span></td>
                                <td class="text-end">{{ number_format($author->stories_count) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($author->views_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu tác giả.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($topAuthors->count() > 5)
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-more-toggle="authors"
                                data-more-label="Xem thêm ({{ $topAuthors->count() - 5 }})" aria-expanded="false">Xem thêm ({{ $topAuthors->count() - 5 }})</button>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>

@push('scripts')
<script>
// "Xem thêm" / "Thu gọn": show or hide the rows after the first five in the same card.
document.querySelectorAll('[data-more-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const rows = document.querySelectorAll('[data-more="' + button.dataset.moreToggle + '"]');
        const expand = button.getAttribute('aria-expanded') !== 'true';
        rows.forEach((row) => row.classList.toggle('d-none', !expand));
        button.setAttribute('aria-expanded', expand ? 'true' : 'false');
        button.textContent = expand ? 'Thu gọn' : button.dataset.moreLabel;
    });
});
</script>
@endpush
@endsection
