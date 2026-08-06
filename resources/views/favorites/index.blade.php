
@extends('layouts.public')

@section('title', 'Truyện yêu thích')

@section('content')

<h4 class="mb-3">Truyện yêu thích của bạn</h4>

<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($stories as $favorite)
        <div class="col"><x-story-card :story="$favorite->story" /></div>
    @empty
        <p class="text-muted">Bạn chưa yêu thích truyện nào. <a href="{{ route('home') }}">Khám phá ngay</a>.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $stories->links() }}
</div>

@endsection
