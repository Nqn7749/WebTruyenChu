
@props(['story'])

<div class="card h-100 story-card-item">
    <a href="{{ route('stories.show', $story) }}" class="text-decoration-none text-dark">
        <div class="position-relative">
            <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
                 class="story-cover"
                 alt="{{ $story->title }}"
                 loading="lazy">

            @if ($story->status === 'completed')
                <span class="badge-status is-completed position-absolute top-0 end-0 m-2">FULL</span>
            @elseif ($story->is_hot ?? false)
                <span class="badge-status is-hot position-absolute top-0 end-0 m-2">HOT</span>
            @endif
        </div>

        <div class="card-body p-2">
            <h6 class="card-title mb-1 text-truncate" title="{{ $story->title }}">
                {{ $story->title }}
            </h6>

            @if ($story->author_name)
                <p class="small mb-1 text-truncate" style="color: var(--muted);">
                    {{ $story->author_name }}
                </p>
            @endif

            <div class="d-flex justify-content-between align-items-center small" style="color: var(--muted);">
                <span><i class="bi bi-eye"></i> {{ number_format($story->views) }}</span>

                @if ($story->average_rating > 0)
                    <span style="color: var(--vermilion);">
                        <i class="bi bi-star-fill"></i> {{ number_format($story->average_rating, 1) }}
                    </span>
                @endif
            </div>

            @if ($story->relationLoaded('categories') && $story->categories->isNotEmpty())
                <div class="mt-2 d-flex flex-wrap gap-1">
                    @foreach ($story->categories->take(2) as $cat)
                        <span class="badge rounded-pill" style="background: var(--jade-tint); color: var(--jade-dark); font-weight: 500; font-size: .68rem;">
                            {{ $cat->name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </a>
</div>
