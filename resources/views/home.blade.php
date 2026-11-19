@extends('layouts.public')

@section('title', 'Đọc truyện chữ online miễn phí - Web Đọc Truyện Chữ')
@section('meta_description', 'Khám phá truyện chữ online miễn phí thuộc nhiều thể loại. Đọc truyện nổi bật, theo dõi chương mới và lưu truyện yêu thích mỗi ngày.')

@section('content')

<section class="home-intro" aria-label="Giới thiệu">
    <div>
        <span class="eyebrow">Không gian đọc của bạn</span>
        <h2>Chọn một câu chuyện, bắt đầu hành trình mới.</h2>
        <p>Khám phá truyện nổi bật, theo dõi chương mới và lưu lại những câu chuyện bạn yêu thích.</p>
    </div>
    <a href="{{ route('search') }}" class="btn btn-outline-jade">
        <i class="bi bi-compass me-1" aria-hidden="true"></i> Khám phá kho truyện
    </a>
</section>

{{-- HERO SPOTLIGHT --}}
@if ($hotStories->isNotEmpty())
    @php $featured = $hotStories->first(); @endphp
    <section class="hero-spotlight mb-5" aria-label="Truyện nổi bật">
        <div class="row g-0 align-items-stretch">
            <div class="col-md-5">
                <div class="hero-cover-wrap">
                    <img src="{{ $featured->cover_image ? Storage::url($featured->cover_image) : asset('images/no-cover.jpg') }}"
                         class="hero-cover" alt="{{ $featured->title }}"
                         onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
                </div>
            </div>
            <div class="col-md-7 p-4 p-lg-5 d-flex flex-column justify-content-center position-relative hero-copy">
                <span class="eyebrow">🔥 Đang hot nhất tuần</span>
                <h1>{{ $featured->title }}</h1>
                <p class="mb-4" style="max-width: 55ch;">
                    {{ Str::limit(strip_tags($featured->description), 160) }}
                </p>
                <div>
                    <a href="{{ route('stories.show', $featured) }}" class="btn btn-vermilion me-2">
                        <i class="bi bi-book"></i> Đọc ngay
                    </a>
                    <a href="{{ route('stories.show', $featured) }}" class="btn btn-outline-light">Xem chi tiết</a>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- HOT: kéo ngang --}}
<div class="section-heading">
    <span class="eyebrow">Bảng xếp hạng</span>
    <h2>Truyện hot</h2>
</div>
<div class="story-scroller mb-5" aria-label="Danh sách truyện" tabindex="0">
    @foreach ($hotStories as $story)
        <x-story-card :story="$story" />
    @endforeach
</div>

{{-- RECOMMENDED --}}
<div class="section-heading">
    <span class="eyebrow">Biên tập chọn</span>
    <h2>Truyện đề cử</h2>
</div>
<div class="story-scroller mb-5">
    @foreach ($recommendedStories as $story)
        <x-story-card :story="$story" />
    @endforeach
</div>

{{-- RECENTLY UPDATED: list dọc --}}
<div class="section-heading">
    <span class="eyebrow">Cập nhật liên tục</span>
    <h2>Mới cập nhật</h2>
    <a href="{{ route('stories.index') }}" class="more-link">Xem thêm →</a>
</div>
<div class="story-list">
    @forelse ($recentlyUpdated as $story)
        <x-story-list-item :story="$story" />
    @empty
        <p class="text-muted p-3 mb-0">Chưa có truyện nào.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $recentlyUpdated->links() }}
</div>

{{-- NEW: MỚI ĐĂNG --}}
<div class="section-heading">
    <span class="eyebrow">Vừa lên sóng</span>
    <h2>Mới đăng</h2>
    <a href="{{ route('stories.index', ['sort' => 'newest_story']) }}" class="more-link">Xem thêm →</a>
</div>
<div class="story-scroller mb-5">
    @forelse ($newStories as $story)
        <x-story-card :story="$story" />
    @empty
        <p class="text-muted p-3 mb-0">Chưa có truyện nào.</p>
    @endforelse
</div>

{{-- NEW: TRUYỆN ĐÃ HOÀN THÀNH --}}
<div class="section-heading">
    <span class="eyebrow">Trọn bộ</span>
    <h2>Truyện đã hoàn thành</h2>
    <a href="{{ route('stories.index', ['status' => 'completed']) }}" class="more-link">Xem thêm →</a>
</div>
<div class="story-scroller mb-5">
    @forelse ($completedStories as $story)
        <x-story-card :story="$story" />
    @empty
        <p class="text-muted p-3 mb-0">Chưa có truyện nào.</p>
    @endforelse
</div>

@endsection