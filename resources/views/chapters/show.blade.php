<x-app-layout>
    <div class="container py-4" style="max-width: 800px;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('stories.show', $story) }}">{{ $story->title }}</a></li>
                <li class="breadcrumb-item active">Chương {{ $chapter->chapter_number }}</li>
            </ol>
        </nav>

        <h3 class="mb-4">
            Chương {{ $chapter->chapter_number }}{{ $chapter->title ? ' - '.$chapter->title : '' }}
        </h3>

        <div class="chapter-content" style="font-size: 1.1rem; line-height: 1.8;">
            {!! nl2br(e($chapter->content)) !!}
        </div>

        <div class="d-flex justify-content-between my-4">
            @if ($prevChapter)
                <a href="{{ route('chapters.show', [$story, $prevChapter]) }}" class="btn btn-outline-secondary">
                    ← Chương trước
                </a>
            @else
                <span></span>
            @endif

            @if ($nextChapter)
                <a href="{{ route('chapters.show', [$story, $nextChapter]) }}" class="btn btn-primary">
                    Chương sau →
                </a>
            @endif
        </div>

        <hr>
        <h5>Bình luận chương</h5>

        @auth
            <form action="{{ route('comments.store', $story) }}" method="POST" class="mb-4">
                @csrf
                <input type="hidden" name="chapter_id" value="{{ $chapter->id }}">
                <textarea name="content" class="form-control mb-2" rows="3" placeholder="Viết bình luận..." required></textarea>
                <button class="btn btn-sm btn-primary">Gửi bình luận</button>
            </form>
        @else
            <p><a href="{{ route('login') }}">Đăng nhập</a> để bình luận.</p>
        @endauth

        @foreach ($comments as $comment)
            <div class="border-bottom py-2">
                <strong>{{ $comment->user->name }}</strong>
                <p class="mb-1">{{ $comment->content }}</p>
                <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
            </div>
        @endforeach

        {{ $comments->links() }}
    </div>
</x-app-layout>