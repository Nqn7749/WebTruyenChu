@extends('layouts.public')

@section('title', 'Trang chủ - Web Đọc Truyện Chữ')

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

{{-- HERO SPOTLIGHT CAROUSEL --}}
@if ($hotStories->isNotEmpty())
    @php
        $spotlightStories = $hotStories->take(5);
    @endphp
    <section id="heroSpotlightCarousel" class="hero-spotlight carousel slide carousel-fade mb-5" data-bs-ride="carousel" data-bs-interval="5500" data-bs-pause="hover" aria-label="Truyện nổi bật">
        @if ($spotlightStories->count() > 1)
            <div class="carousel-indicators hero-carousel-indicators">
                @foreach ($spotlightStories as $index => $story)
                    <button type="button"
                            data-bs-target="#heroSpotlightCarousel"
                            data-bs-slide-to="{{ $index }}"
                            class="{{ $index === 0 ? 'active' : '' }}"
                            aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-label="Truyện hot {{ $index + 1 }}"></button>
                @endforeach
            </div>
        @endif

        <div class="carousel-inner">
            @foreach ($spotlightStories as $index => $story)
                <div class="carousel-item {{ $index === 0 ? 'active' : '' }}" data-bs-interval="5500">
                    <div class="row g-0 align-items-stretch">
                        <div class="col-md-5">
                            <div class="hero-cover-wrap">
                                <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
                                     class="hero-cover" alt="{{ $story->title }}"
                                     onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
                                @if ($story->categories->isNotEmpty())
                                    <div class="hero-category-tag">
                                        {{ $story->categories->first()->name }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-7 p-4 p-lg-5 d-flex flex-column justify-content-center position-relative hero-copy">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="eyebrow">🔥 Đang hot nhất tuần</span>
                                @if ($story->reads_count > 0)
                                    <span class="hero-stat-pill">
                                        <i class="bi bi-eye"></i> {{ number_format($story->reads_count) }} lượt đọc
                                    </span>
                                @endif
                            </div>
                            <h1 class="hero-title">{{ $story->title }}</h1>
                            <p class="mb-4 hero-description" style="max-width: 55ch;">
                                {{ Str::limit(strip_tags($story->description), 160) }}
                            </p>
                            <div class="hero-actions">
                                <a href="{{ route('stories.show', $story) }}" class="btn btn-vermilion me-2">
                                    <i class="bi bi-book"></i> Đọc ngay
                                </a>
                                <a href="{{ route('stories.show', $story) }}" class="btn btn-outline-light">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($spotlightStories->count() > 1)
            <button class="carousel-control-prev hero-carousel-btn" type="button" data-bs-target="#heroSpotlightCarousel" data-bs-slide="prev" aria-label="Truyện trước">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
            <button class="carousel-control-next hero-carousel-btn" type="button" data-bs-target="#heroSpotlightCarousel" data-bs-slide="next" aria-label="Truyện tiếp theo">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        @endif
    </section>
@endif

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