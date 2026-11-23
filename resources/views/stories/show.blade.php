@extends('layouts.public')

@section('title', $story->meta_title ?? $story->title)
@section('meta_description', Str::limit(strip_tags($story->meta_description ?? $story->description ?? ''), 160))
@section('og_type', 'book')
@section('og_image', $story->cover_image ? url(Storage::url($story->cover_image)) : asset('images/no-cover.jpg'))

@section('content')
<div class="row g-4 story-detail-layout">
    <aside class="col-12 col-md-4 col-lg-3 story-detail-sidebar" aria-label="Thông tin truyện">
        <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
             class="story-cover story-detail-cover mb-3" loading="lazy" alt="{{ $story->title }}">

        @auth
            <form action="{{ route('favorites.toggle', $story) }}" method="POST" class="d-grid mb-2">
                @csrf
                <button class="btn {{ $isFavorited ? 'btn-danger' : 'btn-outline-danger' }}">
                    {{ $isFavorited ? '★ Bỏ yêu thích' : '☆ Yêu thích' }}
                </button>
            </form>
            @if (auth()->id() !== $story->user_id)
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none" data-bs-toggle="modal" data-bs-target="#reportStoryModal">
                        <i class="bi bi-flag me-1"></i> Báo cáo vi phạm
                    </button>
                </div>
            @endif
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-danger d-grid mb-2">☆ Yêu thích</a>
        @endauth

        <ul class="list-group small">
            <li class="list-group-item d-flex justify-content-between"><span>Lượt xem</span><strong>{{ number_format($story->views) }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Lượt đọc thật</span><strong>{{ number_format($story->reads_count) }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Yêu thích</span><strong>{{ number_format($story->favorite_count) }}</strong></li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Đánh giá</span><strong>{{ number_format($story->average_rating, 1) }}/5 ({{ $story->rating_count }})</strong>
            </li>
        </ul>
    </aside>

    <section class="col-12 col-md-8 col-lg-9 story-detail-main" aria-labelledby="story-title">
        <h1 id="story-title" class="h2">{{ $story->title }}</h1>
        <p class="text-muted mb-1">Tác giả:
            @if ($story->author_name)
                <a href="{{ route('search', ['author' => $story->author_name]) }}" class="fw-bold text-decoration-none" title="Xem các truyện của tác giả này">{{ $story->author_name }}</a>
            @else
                <strong>Đang cập nhật</strong>
            @endif
        </p>
        @if ($story->user)
            <p class="text-muted mb-2 small">Người đăng:
                <a href="{{ route('search', ['poster' => $story->user_id]) }}" class="text-decoration-none" title="Xem các truyện do người này đăng">{{ $story->user->name }}</a>
            </p>
        @endif

        <div class="mb-3 d-flex flex-wrap gap-2">
            @foreach ($story->categories as $cat)
                <a href="{{ route('categories.show', $cat) }}" class="badge rounded-pill text-decoration-none badge-category">{{ $cat->name }}</a>
            @endforeach
            @foreach ($story->tags as $tag)
                <a href="{{ route('search', ['tag' => $tag->slug]) }}" class="badge rounded-pill bg-light text-muted border text-decoration-none" title="Tìm truyện có tag {{ $tag->name }}">#{{ $tag->name }}</a>
            @endforeach
        </div>

        <div class="story-detail-description mb-4" style="white-space: pre-line;">{{ $story->description }}</div>

        @if ($firstChapter)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 story-detail-actions">
                @if (isset($continueChapter) && $continueChapter)
                    <a href="{{ route('chapters.show', [$story, $continueChapter->slug]) }}" class="btn btn-vermilion btn-sm px-3">
                        <i class="bi bi-bookmark-check me-1"></i> Đọc tiếp chương {{ $continueChapter->chapter_number }}
                    </a>
                @endif
                <a href="{{ route('chapters.show', [$story, $firstChapter->slug]) }}" class="btn btn-jade btn-sm px-3">
                    <i class="bi bi-book me-1"></i> Đọc từ đầu
                </a>
                <a href="{{ route('chapters.show', [$story, $latestChapter->slug]) }}" class="btn btn-outline-jade btn-sm px-3">
                    Chương mới nhất ({{ $latestChapter->chapter_number }})
                </a>
            </div>
        @endif

        {{-- Quảng cáo tài trợ an toàn cho mobile & laptop --}}
        <x-ad-banner page="story" />

        <h5 id="chapterList">Danh sách chương ({{ number_format($chapters->total()) }})</h5>
        <form action="{{ route('chapters.jump', $story) }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Nhảy tới chương">
            <label for="chapterJump" class="visually-hidden">Số chương</label>
            <input type="number" id="chapterJump" name="so" min="1" max="99999999" required
                   class="form-control form-control-sm w-auto" style="width: 9rem !important" placeholder="Số chương"
                   value="{{ old('so') }}">
            <button type="submit" class="btn btn-sm btn-outline-jade">Nhảy tới chương</button>
            @if (session('chapter_jump_error'))
                <span class="text-danger small" role="alert">{{ session('chapter_jump_error') }}</span>
            @endif
            @error('so')
                <span class="text-danger small" role="alert">Số chương không hợp lệ.</span>
            @enderror
        </form>
        <div class="list-group mb-3 chapter-list" aria-label="Danh sách chương">
            @forelse ($chapters as $chapter)
                <a href="{{ route('chapters.show', [$story, $chapter->slug]) }}"
                   class="list-group-item list-group-item-action chapter-list-item d-flex justify-content-between">
                    <span>Chương {{ $chapter->chapter_number }}{{ $chapter->title ? ' - '.$chapter->title : '' }}</span>
                    <small class="text-muted">{{ number_format($chapter->views) }} lượt xem</small>
                </a>
            @empty
                <p class="text-muted p-3">Chưa có chương nào.</p>
            @endforelse
        </div>
        <div class="d-flex justify-content-center mb-4">{{ $chapters->fragment('chapterList')->links() }}</div>

        <h5 class="mt-4">Đánh giá truyện</h5>
        @auth
            <form action="{{ route('ratings.store', $story) }}" method="POST" class="mb-4 star-rating-form">
                @csrf
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="star-rating" role="radiogroup" aria-label="Đánh giá truyện">
                        @for ($i = 5; $i >= 1; $i--)
                            <input type="radio" class="visually-hidden star-radio" name="score" id="score{{ $i }}" value="{{ $i }}"
                                   {{ $userRating == $i ? 'checked' : '' }} autocomplete="off">
                            <label class="star-label" for="score{{ $i }}" title="{{ $i }} sao" aria-label="{{ $i }} sao">
                                <i class="bi bi-star-fill"></i>
                            </label>
                        @endfor
                    </div>
                    <span class="star-rating-hint text-muted small ms-2">
                        @if ($userRating)
                            (Bạn đã đánh giá {{ $userRating }}/5 sao)
                        @else
                            (Chọn số sao để đánh giá)
                        @endif
                    </span>
                </div>
                <div><button type="submit" class="btn btn-sm btn-jade">Gửi đánh giá</button></div>
                @error('score') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </form>
        @else
            <p class="text-muted"><a href="{{ route('login') }}" class="text-decoration-none fw-semibold">Đăng nhập</a> để đánh giá truyện.</p>
        @endauth

        <section id="commentsSection">
            <h5>Bình luận ({{ $story->comment_count }})</h5>
            @auth
                <form id="commentForm" action="{{ route('comments.store', $story) }}" method="POST" class="mb-3">
                    @csrf
                    <label for="commentContent" class="visually-hidden">Nội dung bình luận</label>
                    <textarea id="commentContent" name="content" class="form-control mb-2" rows="3"
                              placeholder="Viết bình luận..." required maxlength="2000"></textarea>
                    <button type="submit" id="commentSubmit" class="btn btn-sm btn-jade">Gửi bình luận</button>
                </form>
            @else
                <p><a href="{{ route('login') }}">Đăng nhập</a> để bình luận.</p>
            @endauth
            @include('components.comments', compact('comments'))
            @if ($comments->hasPages())
                <div class="mt-4">{{ $comments->fragment('commentsSection')->links() }}</div>
            @endif
        </section>
    </section>
</div>

@if ($relatedStories->isNotEmpty())
    <section class="mt-5" aria-labelledby="related-stories-heading">
        <div class="section-heading">
            <span class="eyebrow">Có thể bạn sẽ thích</span>
            <h2 id="related-stories-heading">Truyện liên quan</h2>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
            @foreach ($relatedStories as $relatedStory)
                <div class="col"><x-story-card :story="$relatedStory" /></div>
            @endforeach
        </div>
    </section>
@endif

@auth
    @if (auth()->id() !== $story->user_id)
        {{-- Modal Báo cáo truyện --}}
        <div class="modal fade" id="reportStoryModal" tabindex="-1" aria-labelledby="reportStoryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('reports.stories.store', $story) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title h6" id="reportStoryModalLabel">
                                <i class="bi bi-flag text-danger me-1"></i> Báo cáo truyện vi phạm
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="story-report-reason" class="form-label small fw-semibold">Lý do báo cáo</label>
                                <select id="story-report-reason" name="reason" class="form-select form-select-sm" required>
                                    <option value="spam">Spam / quảng cáo</option>
                                    <option value="abuse">Quấy rối / xúc phạm</option>
                                    <option value="copyright">Vi phạm bản quyền</option>
                                    <option value="inappropriate">Nội dung không phù hợp</option>
                                    <option value="other">Lý do khác</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="story-report-details" class="form-label small fw-semibold">Mô tả thêm (không bắt buộc)</label>
                                <textarea id="story-report-details" name="details" class="form-control form-control-sm" rows="3" maxlength="1000"
                                          placeholder="Cung cấp thêm chi tiết vi phạm..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-sm btn-danger">Gửi báo cáo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endauth
@endsection
