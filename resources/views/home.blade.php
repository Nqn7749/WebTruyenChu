@extends('layouts.public')

@section('title', 'Trang chủ - Web Đọc Truyện Chữ')

@section('content')

{{-- HERO SPOTLIGHT --}}
@if ($hotStories->isNotEmpty())
    @php $featured = $hotStories->first(); @endphp
    <div class="hero-spotlight mb-5">
        <div class="row g-0 align-items-stretch">
            <div class="col-md-5">
                <div class="hero-cover-wrap">
                    <img src="{{ $featured->cover_image ? Storage::url($featured->cover_image) : asset('images/no-cover.jpg') }}"
                         class="hero-cover" alt="{{ $featured->title }}"
                         onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
                </div>
            </div>
            <div class="col-md-7 p-4 p-lg-5 d-flex flex-column justify-content-center position-relative">
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
    </div>
@endif

{{-- HOT: kéo ngang --}}
<div class="section-heading">
    <span class="eyebrow">Bảng xếp hạng</span>
    <h2>Truyện hot</h2>
</div>
<div class="story-scroller mb-5">
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

@endsection