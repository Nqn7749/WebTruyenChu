@extends('layouts.public')

@section('title', 'Trang chủ - Web Đọc Truyện Chữ')

@section('content')

<h5 class="mb-3">🔥 Truyện hot</h5>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3 mb-4">
    @foreach ($hotStories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @endforeach
</div>

<h5 class="mb-3">⭐ Truyện đề cử</h5>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3 mb-4">
    @foreach ($recommendedStories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @endforeach
</div>

<h5 class="mb-3">🆕 Mới cập nhật</h5>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($recentlyUpdated as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @empty
        <p class="text-muted">Chưa có truyện nào.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $recentlyUpdated->links() }}
</div>

@endsection