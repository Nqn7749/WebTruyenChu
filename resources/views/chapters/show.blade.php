@extends('layouts.public')

@section('title', $chapter->title
    ? "Chương {$chapter->chapter_number} - {$chapter->title} - {$story->title}"
    : "Chương {$chapter->chapter_number} - {$story->title}")

@section('meta_description', Str::limit(strip_tags($chapter->content), 160))
@section('og_type', 'article')
@section('og_image', $story->cover_image
    ? url(Storage::url($story->cover_image))
    : asset('images/no-cover.jpg'))

@section('content')

<div class="reading-progress-bar" id="readingProgressBar"></div>

<div class="mx-auto" style="max-width: 900px;">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="small mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('stories.show', $story) }}">
                    {{ $story->title }}
                </a>
            </li>

            <li class="breadcrumb-item active">
                Chương {{ $chapter->chapter_number }}
            </li>
        </ol>
    </nav>

    {{-- Nội dung chương --}}
    <article class="reader-wrap"
             id="readerWrap"
             data-reader-theme="light">

        <span class="reader-chapter-badge">
            Chương {{ $chapter->chapter_number }}
        </span>

        <h1 class="reader-chapter-title">
            {{ $chapter->title ?: 'Không tiêu đề' }}
        </h1>

        <div class="chapter-content"
             id="chapterContent"
             style="white-space: pre-line;">
            {{ $chapter->content }}
        </div>

    </article>

    {{-- Thanh công cụ --}}
    <div class="reader-toolbar">

        @if ($prevChapter)
            <a href="{{ route('chapters.show', [$story, $prevChapter]) }}"
               title="Chương trước"
               aria-label="Chương trước">
                <i class="bi bi-chevron-left"></i>
            </a>
        @endif

        <div class="divider"></div>

        <button type="button"
                id="fontDecrease"
                title="Giảm cỡ chữ"
                aria-label="Giảm cỡ chữ">
            <i class="bi bi-dash-lg"></i>
        </button>

        <button type="button"
                id="fontIncrease"
                title="Tăng cỡ chữ"
                aria-label="Tăng cỡ chữ">
            <i class="bi bi-plus-lg"></i>
        </button>

        <div class="divider"></div>

        <button type="button"
                class="theme-btn active"
                data-theme="light"
                title="Sáng"
                aria-label="Giao diện sáng">
            <i class="bi bi-sun"></i>
        </button>

        <button type="button"
                class="theme-btn"
                data-theme="sepia"
                title="Sepia"
                aria-label="Giao diện sepia">
            <i class="bi bi-circle-half"></i>
        </button>

        <button type="button"
                class="theme-btn"
                data-theme="dark"
                title="Tối"
                aria-label="Giao diện tối">
            <i class="bi bi-moon-stars"></i>
        </button>

        <div class="divider"></div>

        @if ($nextChapter)
            <a href="{{ route('chapters.show', [$story, $nextChapter]) }}"
               title="Chương sau"
               aria-label="Chương sau">
                <i class="bi bi-chevron-right"></i>
            </a>
        @endif

    </div>

    {{-- Chuyển chương --}}
    <div class="d-flex justify-content-between my-4">

        @if ($prevChapter)
            <a href="{{ route('chapters.show', [$story, $prevChapter]) }}"
               class="btn btn-outline-jade">
                ← Chương trước
            </a>
        @else
            <span></span>
        @endif

        @if ($nextChapter)
            <a href="{{ route('chapters.show', [$story, $nextChapter]) }}"
               class="btn btn-jade">
                Chương sau →
            </a>
        @endif

    </div>

    <hr>

{{-- =========================================================
    COMMENTS
========================================================= --}}

<section id="commentsSection">

    <div class="section-heading">
        <h2 class="fs-5">
            Bình luận chương
        </h2>
    </div>

    {{-- Comment form --}}
    @auth

        <form
            id="commentForm"
            action="{{ route('comments.store', $story) }}"
            method="POST"
            class="mb-4"
        >
            @csrf

            <input
                type="hidden"
                name="chapter_id"
                value="{{ $chapter->id }}"
            >

            <div class="mb-2">

                <textarea
                    id="commentContent"
                    name="content"
                    class="form-control"
                    rows="3"
                    placeholder="Viết bình luận của bạn..."
                    required
                    maxlength="2000"
                ></textarea>

            </div>

            <button
                type="submit"
                id="commentSubmit"
                class="btn btn-jade"
            >
                <i class="bi bi-chat-left-text"></i>
                Bình luận
            </button>

        </form>

    @else

        <div class="alert alert-light border mb-4">

            <i class="bi bi-info-circle"></i>

            Vui lòng
            <a href="{{ route('login') }}">
                đăng nhập
            </a>
            để bình luận.

        </div>

    @endauth


    {{-- Comments --}}
    @include('components.comments', [
        'comments' => $comments
    ])


    {{-- Pagination --}}
    @if ($comments->hasPages())
        <div class="mt-4">
            {{ $comments->links() }}
        </div>
    @endif

</section>


{{-- Toast --}}
<div
    class="toast-container position-fixed top-0 end-0 p-3"
    style="z-index: 1100;"
>
    <div
        id="commentToast"
        class="toast border-0"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
    >
        <div class="d-flex">

            <div
                id="commentToastMessage"
                class="toast-body"
            ></div>

            <button
                type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"
                aria-label="Đóng"
            ></button>

        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const wrap = document.getElementById('readerWrap');
    const content = document.getElementById('chapterContent');
    const progressBar = document.getElementById('readingProgressBar');

    if (!wrap || !content || !progressBar) {
        return;
    }

    const chapterId = {{ $chapter->id }};
    const storyId = {{ $story->id }};

    const storageKeys = {
        position: `chapter_${chapterId}`,
        fontSize: 'reader_font_size',
        theme: 'reader_theme'
    };


    /* =========================================================
       UTILITY
    ========================================================= */

    function debounce(callback, delay = 500) {
        let timer;

        return function (...args) {
            clearTimeout(timer);

            timer = setTimeout(() => {
                callback.apply(this, args);
            }, delay);
        };
    }


    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }


    /* =========================================================
       READING PROGRESS
    ========================================================= */

    let lastPercent = -1;
    let lastSavedPercent = -1;

    function getReadingPercent() {
        const scrollableHeight =
            document.documentElement.scrollHeight -
            window.innerHeight;

        if (scrollableHeight <= 0) {
            return 100;
        }

        return Math.round(
            (window.scrollY / scrollableHeight) * 100
        );
    }


    function updateProgressBar() {
        const percent = getReadingPercent();

        progressBar.style.width = `${percent}%`;

        return percent;
    }


    /* =========================================================
       SERVER READING HISTORY
    ========================================================= */

    @auth

    const saveProgressToServer = debounce(async (percent) => {

        if (percent === lastSavedPercent) {
            return;
        }

        lastSavedPercent = percent;

        try {

            await fetch(
                '{{ route('reading-history.update-progress') }}',
                {
                    method: 'PATCH',

                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            ).content,
                        'Accept': 'application/json'
                    },

                    body: JSON.stringify({
                        story_id: storyId,
                        chapter_id: chapterId,
                        scroll_percent: percent
                    }),

                    keepalive: true
                }
            );

        } catch (error) {
            // Không làm gián đoạn quá trình đọc
            console.debug(
                'Không thể cập nhật tiến độ đọc.',
                error
            );
        }

    }, 1500);

    @else

    function saveProgressToServer() {}

    @endauth


    /* =========================================================
       SCROLL HANDLER
    ========================================================= */

    const handleScroll = () => {

        const percent = updateProgressBar();

        // Lưu vị trí đọc local
        localStorage.setItem(
            storageKeys.position,
            String(window.scrollY)
        );

        // Chỉ xử lý khi phần trăm thực sự thay đổi
        if (percent !== lastPercent) {

            lastPercent = percent;

            @auth
            saveProgressToServer(percent);
            @endauth
        }
    };


    window.addEventListener(
        'scroll',
        handleScroll,
        { passive: true }
    );


    /* =========================================================
       RESTORE SCROLL POSITION
    ========================================================= */

    function restoreReadingPosition() {

        const savedPosition =
            localStorage.getItem(
                storageKeys.position
            );

        if (!savedPosition) {
            updateProgressBar();
            return;
        }

        const position =
            parseInt(savedPosition, 10);

        if (Number.isNaN(position) || position < 0) {
            updateProgressBar();
            return;
        }

        requestAnimationFrame(() => {

            window.scrollTo({
                top: position,
                behavior: 'instant'
            });

            updateProgressBar();
        });
    }


    if (document.readyState === 'complete') {
        restoreReadingPosition();
    } else {
        window.addEventListener(
            'load',
            restoreReadingPosition,
            { once: true }
        );
    }


    /* =========================================================
       FONT SIZE
    ========================================================= */

    let fontSize =
        parseFloat(
            localStorage.getItem(
                storageKeys.fontSize
            )
        ) || 1.15;


    function applyFontSize() {

        fontSize = clamp(
            fontSize,
            0.9,
            1.8
        );

        content.style.setProperty(
            '--reader-font-size',
            `${fontSize}rem`
        );
    }


    function saveFontSize() {

        localStorage.setItem(
            storageKeys.fontSize,
            fontSize.toString()
        );
    }


    document
        .getElementById('fontIncrease')
        ?.addEventListener('click', () => {

            fontSize += 0.1;

            applyFontSize();
            saveFontSize();

            updateProgressBar();
        });


    document
        .getElementById('fontDecrease')
        ?.addEventListener('click', () => {

            fontSize -= 0.1;

            applyFontSize();
            saveFontSize();

            updateProgressBar();
        });


    applyFontSize();


    /* =========================================================
       READER THEME
    ========================================================= */

    const themeButtons =
        document.querySelectorAll('.theme-btn');


    function applyTheme(theme) {

        const allowedThemes = [
            'light',
            'sepia',
            'dark'
        ];

        if (!allowedThemes.includes(theme)) {
            theme = 'light';
        }

        wrap.dataset.readerTheme = theme;

        localStorage.setItem(
            storageKeys.theme,
            theme
        );

        themeButtons.forEach(button => {

            const isActive =
                button.dataset.theme === theme;

            button.classList.toggle(
                'active',
                isActive
            );

            button.setAttribute(
                'aria-pressed',
                isActive
            );

        });
    }


    const savedTheme =
        localStorage.getItem(
            storageKeys.theme
        ) || 'light';


    applyTheme(savedTheme);


    themeButtons.forEach(button => {

        button.addEventListener(
            'click',
            () => {
                applyTheme(
                    button.dataset.theme
                );
            }
        );

    });


    /* =========================================================
       SAVE LAST POSITION BEFORE LEAVING
    ========================================================= */

    window.addEventListener(
        'beforeunload',
        () => {

            localStorage.setItem(
                storageKeys.position,
                String(window.scrollY)
            );

            @auth

            const percent =
                getReadingPercent();

            if (percent !== lastSavedPercent) {

                fetch(
                    '{{ route('reading-history.update-progress') }}',
                    {
                        method: 'PATCH',

                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector(
                                    'meta[name="csrf-token"]'
                                ).content,
                            'Accept': 'application/json'
                        },

                        body: JSON.stringify({
                            story_id: storyId,
                            chapter_id: chapterId,
                            scroll_percent: percent
                        }),

                        keepalive: true
                    }
                );

            }

            @endauth
        }
    );


    /* =========================================================
       INITIAL PROGRESS
    ========================================================= */

    updateProgressBar();

        /* =========================================================
       AJAX COMMENT
    ========================================================= */

    const commentForm = document.getElementById('commentForm');

    commentForm?.addEventListener('submit', async function (event) {
        event.preventDefault();

        const form = this;
        const button = document.getElementById('commentSubmit');
        const textarea = document.getElementById('commentContent');
        const commentsList = document.getElementById('commentsList');
        const emptyComments = document.getElementById('emptyComments');

        button.disabled = true;

        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-1"></span>
            Đang gửi...
        `;

        try {
            const response = await fetch(form.action, {
                method: 'POST',

                headers: {
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },

                body: new FormData(form),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Không thể gửi bình luận.'
                );
            }

            // Xóa trạng thái "chưa có bình luận".
            emptyComments?.remove();

            // Thêm HTML từ Laravel Partial.
            commentsList.insertAdjacentHTML(
                'afterbegin',
                data.html
            );

            const newComment =
                commentsList.firstElementChild;
            newComment?.classList.add('comment-new');

            textarea.value = '';

            showCommentToast(
                data.message || 'Đã gửi bình luận.',
                'success'
            );

        } catch (error) {

            console.error(error);

            showCommentToast(
                error.message || 'Không thể gửi bình luận.',
                'danger'
            );

        } finally {

            button.disabled = false;

            button.innerHTML = `
                <i class="bi bi-chat-left-text"></i>
                Bình luận
            `;
        }
    });

    function showCommentToast(message, type = 'success') {
        const toastEl = document.getElementById('commentToast');
        const toastMessage = document.getElementById('commentToastMessage');

        toastEl.classList.remove(
            'text-bg-success',
            'text-bg-danger',
            'text-bg-warning'
        );

        toastEl.classList.add(`text-bg-${type}`);
        toastMessage.textContent = message;

        bootstrap.Toast.getOrCreateInstance(toastEl, {
            delay: 3000
        }).show();
    }

    document.addEventListener('click', async function (event) {

        const button = event.target.closest('.delete-comment-btn');

        if (!button) {
            return;
        }

        const confirmed = confirm(
            'Bạn có chắc muốn xóa bình luận này?'
        );

        if (!confirmed) {
            return;
        }

        button.disabled = true;

        try {

            const response = await fetch(
                button.dataset.url,
                {
                    method: 'DELETE',

                    headers: {
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .content,

                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Không thể xóa bình luận.'
                );
            }

            document
                .getElementById(
                    `comment-${button.dataset.commentId}`
                )
                ?.remove();

            showCommentToast(
                data.message || 'Đã xóa bình luận.',
                'success'
            );

        } catch (error) {

            console.error(error);

            button.disabled = false;

            showCommentToast(
                error.message || 'Không thể xóa bình luận.',
                'danger'
            );
        }
    });
})();
</script>
@endpush

@endsection