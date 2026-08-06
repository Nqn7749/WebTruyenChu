
@extends('layouts.public')

@section('title', 'Trang chủ - Web Đọc Truyện Chữ')

@section('content')

{{-- HERO SPOTLIGHT --}}
@if ($hotStories->isNotEmpty())
    @php $featured = $hotStories->first(); @endphp
    <div class="hero-spotlight mb-5">
        <div class="row g-0 align-items-stretch">
            <div class="col-md-5">
                <img src="{{ $featured->cover_image ? Storage::url($featured->cover_image) : asset('images/no-cover.jpg') }}"
                     class="hero-cover" alt="{{ $featured->title }}">
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

{{-- HOT --}}
<div class="section-heading">
    <span class="eyebrow">Bảng xếp hạng</span>
    <h2>Truyện hot</h2>
</div>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3 mb-5">
    @foreach ($hotStories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @endforeach
</div>

{{-- RECOMMENDED --}}
<div class="section-heading">
    <span class="eyebrow">Biên tập chọn</span>
    <h2>Truyện đề cử</h2>
</div>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3 mb-5">
    @foreach ($recommendedStories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @endforeach
</div>

{{-- RECENTLY UPDATED --}}
<div class="section-heading">
    <span class="eyebrow">Cập nhật liên tục</span>
    <h2>Mới cập nhật</h2>
</div>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($recentlyUpdated as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @empty
        <p style="color: var(--muted);">Chưa có truyện nào.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $recentlyUpdated->links() }}
</div>

@endsection
