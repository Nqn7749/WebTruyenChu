
@extends('layouts.public')

@section('title', 'Lịch sử đọc truyện')

@section('content')

<h4 class="mb-3">Lịch sử đọc truyện</h4>

<div class="list-group">
    @forelse ($histories as $history)
        @if ($history->story && $history->chapter)
            <a href="{{ route('chapters.show', [$history->story, $history->chapter]) }}"
               class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                <img src="{{ $history->story->cover_image ? Storage::url($history->story->cover_image) : asset('images/no-cover.jpg') }}"
                     style="width: 50px; height: 66px; object-fit: cover; border-radius: .25rem;">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $history->story->title }}</div>
                    <div class="small text-muted">
                        Đang đọc: Chương {{ $history->chapter->chapter_number }}
                        @if ($history->chapter->title) - {{ $history->chapter->title }} @endif
                    </div>
                </div>
                <small class="text-muted">{{ $history->read_at->diffForHumans() }}</small>
            </a>
        @endif
    @empty
        <p class="text-muted">Bạn chưa đọc truyện nào. <a href="{{ route('home') }}">Bắt đầu đọc ngay</a>.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $histories->links() }}
</div>

@endsection
