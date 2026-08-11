@extends('layouts.public')

@section('title', $category->name)

@section('meta_description', $category->description
    ? Str::limit(strip_tags($category->description), 160)
    : "Danh sách truyện thể loại {$category->name} — cập nhật liên tục tại Web Đọc Truyện Chữ.")

@section('content')

<h4 class="mb-3">Thể loại: {{ $category->name }}</h4>
@if ($category->description)
    <p class="text-muted">{{ $category->description }}</p>
@endif

<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($stories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @empty
        <p class="text-muted">Chưa có truyện nào trong thể loại này.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $stories->links() }}
</div>

@endsection
