@extends('layouts.public')

@section('title', $chapter->title
    ? "Chương {$chapter->chapter_number} - {$chapter->title} - {$story->title}"
    : "Chương {$chapter->chapter_number} - {$story->title}")

@section('meta_description', Str::limit(strip_tags($chapter->content), 160))
@section('og_type', 'article')
@section('og_image', $story->cover_image ? url(Storage::url($story->cover_image)) : asset('images/no-cover.jpg'))

@section('content')

<div class="reading-progress-bar" id="readingProgressBar"></div>

<div class="mx-auto" style="max-width: 900px;">
    <nav aria-label="breadcrumb" class="small mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('stories.show', $story) }}">{{ $story->title }}</a></li>
            <li class="breadcrumb-item active">Chương {{ $chapter->chapter_number }}</li>
        </ol>
    </nav>

    <div class="reader-wrap" id="readerWrap" data-reader-theme="light">
        <span class="reader-chapter-badge">Chương {{ $chapter->chapter_number }}</span>
        <h1 class="reader-chapter-title">
            {{ $chapter->title ?: 'Không tiêu đề' }}
        </h1>

        <div class="chapter-content" id="chapterContent" style="white-space: pre-line;">{{ $chapter->content }}</div>
    </div>

    <div class="reader-toolbar">
        @if ($prevChapter)
            <a href="{{ route('chapters.show', [$story, $prevChapter]) }}" title="Chương trước">
                <i class="bi bi-chevron-left"></i>
            </a>
        @endif

        <div class="divider"></div>

        <button type="button" id="fontDecrease" title="Giảm cỡ chữ"><i class="bi bi-dash-lg"></i></button>
        <button type="button" id="fontIncrease" title="Tăng cỡ chữ"><i class="bi bi-plus-lg"></i></button>

        <div class="divider"></div>

        <button type="button" class="theme-btn active" data-theme="light" title="Sáng"><i class="bi bi-sun"></i></button>
        <button type="button" class="theme-btn" data-theme="sepia" title="Sepia"><i class="bi bi-circle-half"></i></button>
        <button type="button" class="theme-btn" data-theme="dark" title="Tối"><i class="bi bi-moon-stars"></i></button>

        <div class="divider"></div>

        @if ($nextChapter)
            <a href="{{ route('chapters.show', [$story, $nextChapter]) }}" title="Chương sau">
                <i class="bi bi-chevron-right"></i>
            </a>
        @endif
    </div>

    <div class="d-flex justify-content-between my-4">
        @if ($prevChapter)
            <a href="{{ route('chapters.show', [$story, $prevChapter]) }}" class="btn btn-outline-jade">
                ← Chương trước
            </a>
        @else
            <span></span>
        @endif

        @if ($nextChapter)
            <a href="{{ route('chapters.show', [$story, $nextChapter]) }}" class="btn btn-jade">
                Chương sau →
            </a>
        @endif
    </div>

    <hr>
    <div class="section-heading"><h2 class="fs-5">Bình luận chương</h2></div>

    @auth
        <form action="{{ route('comments.store', $story) }}" method="POST" class="mb-4">
            @csrf
            <input type="hidden" name="chapter_id" value="{{ $chapter->id }}">
            <textarea name="content" class="form-control mb-2" rows="3" placeholder="Viết bình luận..." required></textarea>
            <button class="btn btn-jade btn-sm">Gửi bình luận</button>
        </form>
    @else
        <p><a href="{{ route('login') }}">Đăng nhập</a> để bình luận.</p>
    @endauth

    @foreach ($comments as $comment)
        <div class="border-bottom py-2">
            <strong>{{ $comment->user->name }}</strong>
            <span class="small" style="color: var(--muted);">{{ $comment->created_at->diffForHumans() }}</span>
            <p class="mb-1">{{ $comment->content }}</p>
        </div>
    @endforeach

    {{ $comments->links() }}
</div>

@push('scripts')
<script>
(function () {
    const wrap = document.getElementById('readerWrap');
    const content = document.getElementById('chapterContent');
    const bar = document.getElementById('readingProgressBar');

    // --- Reading progress bar ---
    window.addEventListener('scroll', function () {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        bar.style.width = pct + '%';
    });

    // --- Font size (lưu trong localStorage) ---
    let fontSize = parseFloat(localStorage.getItem('reader_font_size')) || 1.15;
    const applyFontSize = () => content.style.setProperty('--reader-font-size', fontSize + 'rem');
    applyFontSize();

    document.getElementById('fontIncrease').addEventListener('click', () => {
        fontSize = Math.min(fontSize + 0.1, 1.8);
        localStorage.setItem('reader_font_size', fontSize);
        applyFontSize();
    });
    document.getElementById('fontDecrease').addEventListener('click', () => {
        fontSize = Math.max(fontSize - 0.1, 0.9);
        localStorage.setItem('reader_font_size', fontSize);
        applyFontSize();
    });

    // --- Theme sáng / sepia / tối ---
    const themeButtons = document.querySelectorAll('.theme-btn');
    const applyTheme = (theme) => {
        wrap.setAttribute('data-reader-theme', theme);
        localStorage.setItem('reader_theme', theme);
        themeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.theme === theme));
    };

    const savedTheme = localStorage.getItem('reader_theme') || 'light';
    applyTheme(savedTheme);

    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => applyTheme(btn.dataset.theme));
    });

    window.addEventListener('scroll',()=>{
    localStorage.setItem(
        'chapter_'+{{ $chapter->id }},
        window.scrollY
    );
});

window.addEventListener('load',()=>{
    const pos=
    localStorage.getItem(
        'chapter_'+{{ $chapter->id }}
    );

    if(pos){
        window.scrollTo(0,pos);
    }
});

})();
</script>
@endpush

@endsection
