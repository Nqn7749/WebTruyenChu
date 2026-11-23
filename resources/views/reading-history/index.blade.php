@extends('layouts.public')

@section('title', 'Lịch sử đọc truyện')

@section('content')

<div class="section-heading">
    <span class="eyebrow">Tủ truyện cá nhân</span>
    <h2>Lịch sử đọc truyện</h2>
</div>

<div class="list-group shadow-sm border-0" style="border-radius: var(--radius);">
    @forelse ($histories as $history)
        @if ($history->story && $history->chapter)
            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 border-bottom">
                <a href="{{ route('stories.show', $history->story) }}" class="flex-shrink-0">
                    <img src="{{ $history->story->cover_image ? Storage::url($history->story->cover_image) : asset('images/no-cover.jpg') }}"
                         style="width: 56px; height: 75px; object-fit: cover; border-radius: var(--radius-sm);"
                         alt="{{ $history->story->title }}"
                         onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
                </a>
                <div class="flex-grow-1 min-w-0">
                    <a href="{{ route('stories.show', $history->story) }}" class="fw-semibold text-decoration-none text-dark d-block text-truncate mb-1">
                        {{ $history->story->title }}
                    </a>
                    <div class="small text-muted mb-2">
                        <span>Đang đọc: </span>
                        <a href="{{ route('chapters.show', [$history->story, $history->chapter->slug]) }}" class="text-decoration-none fw-semibold">
                            Chương {{ $history->chapter->chapter_number }}{{ $history->chapter->title ? ' - '.$history->chapter->title : '' }}
                        </a>
                    </div>
                    @php($percent = max(0, min(100, (int) ($history->scroll_percent ?? 0))))
                    <div class="d-flex align-items-center gap-2" style="max-width: 260px;">
                        <div class="progress flex-grow-1" style="height: 6px; background-color: var(--sand-border);">
                            <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%; background-color: var(--jade);"
                                 aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="small text-muted" style="font-size: .75rem;">{{ $percent }}%</span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                    <small class="text-muted">{{ $history->read_at->diffForHumans() }}</small>
                    <a href="{{ route('chapters.show', [$history->story, $history->chapter->slug]) }}" class="btn btn-sm btn-outline-jade">
                        Đọc tiếp
                    </a>
                </div>
            </div>
        @endif
    @empty
        <div class="p-4 text-center text-muted">
            <p class="mb-2">Bạn chưa đọc truyện nào.</p>
            <a href="{{ route('home') }}" class="btn btn-jade btn-sm">Bắt đầu khám phá ngay</a>
        </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $histories->links() }}
</div>

@endsection
