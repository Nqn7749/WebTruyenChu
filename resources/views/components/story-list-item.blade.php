@props(['story'])

<a href="{{ route('stories.show', $story) }}" class="story-list-item">
    <div class="story-list-item__cover">
        <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
             alt="{{ $story->title }}"
             loading="lazy"
             onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
    </div>

    <div class="story-list-item__main">
        <span class="story-list-item__title">
            {{ $story->title }}
            @if ($story->status === 'completed')
                <span class="badge-mini is-completed">FULL</span>
            @endif
        </span>

        @if ($story->relationLoaded('categories') && $story->categories->isNotEmpty())
            <span class="story-list-item__cats">
                {{ $story->categories->pluck('name')->join(', ') }}
            </span>
        @endif
    </div>

    <div class="story-list-item__chapter">
        Chương {{ number_format($story->chapter_count) }}
    </div>

    <div class="story-list-item__time">
        {{ $story->last_chapter_at?->diffForHumans(null, true) ?? '—' }}
    </div>
</a>