
@extends('layouts.public')

@section('title', $story->meta_title ?? $story->title)

@section('meta_description', Str::limit(strip_tags($story->meta_description ?? $story->description ?? ''), 160))
@section('og_type', 'book')
@section('og_image', $story->cover_image ? url(Storage::url($story->cover_image)) : asset('images/no-cover.jpg'))

@section('content')

<div class="row">
    <div class="col-md-3">
        <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
             class="story-cover w-100 mb-3" alt="{{ $story->title }}">

        @auth
            <form action="{{ route('favorites.toggle', $story) }}" method="POST" class="d-grid mb-2">
                @csrf
                <button class="btn {{ $isFavorited ? 'btn-danger' : 'btn-outline-danger' }}">
                    {{ $isFavorited ? '★ Bỏ yêu thích' : '☆ Yêu thích' }}
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-danger d-grid mb-2">☆ Yêu thích</a>
        @endauth

        <ul class="list-group small">
            <li class="list-group-item d-flex justify-content-between">
                <span>Lượt xem</span><strong>{{ number_format($story->views) }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Yêu thích</span><strong>{{ number_format($story->favorite_count) }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Đánh giá</span>
                <strong>{{ number_format($story->average_rating, 1) }}/5 ({{ $story->rating_count }})</strong>
            </li>
        </ul>
    </div>

    <div class="col-md-9">
        <h3>{{ $story->title }}</h3>
        <p class="text-muted mb-2">
            Tác giả: <strong>{{ $story->author_name ?? 'Đang cập nhật' }}</strong>
        </p>

        <div class="mb-3">
            @foreach ($story->categories as $cat)
                <a href="{{ route('categories.show', $cat) }}" class="badge bg-primary text-decoration-none">{{ $cat->name }}</a>
            @endforeach
            @foreach ($story->tags as $tag)
                <span class="badge bg-light text-dark border">{{ $tag->name }}</span>
            @endforeach
        </div>

        <p style="white-space: pre-line;">{{ $story->description }}</p>

        @if ($chapters->isNotEmpty())
            <div class="d-flex gap-2 mb-3">
                <a href="{{ route('chapters.show', [$story, $chapters->first()]) }}" class="btn btn-primary btn-sm">
                    Đọc từ đầu
                </a>
                <a href="{{ route('chapters.show', [$story, $chapters->last()]) }}" class="btn btn-outline-primary btn-sm">
                    Chương mới nhất
                </a>
            </div>
        @endif

        <h5>Danh sách chương ({{ $chapters->count() }})</h5>
        <div class="list-group mb-4" style="max-height: 400px; overflow-y: auto;">
            @forelse ($chapters as $chapter)
                <a href="{{ route('chapters.show', [$story, $chapter]) }}"
                   class="list-group-item list-group-item-action chapter-list-item d-flex justify-content-between">
                    <span>Chương {{ $chapter->chapter_number }}{{ $chapter->title ? ' - '.$chapter->title : '' }}</span>
                    <small class="text-muted">{{ number_format($chapter->views) }} lượt xem</small>
                </a>
            @empty
                <p class="text-muted p-3">Chưa có chương nào.</p>
            @endforelse
        </div>

        {{-- Đánh giá --}}
        <h5>Đánh giá truyện</h5>
        @auth
            <form action="{{ route('ratings.store', $story) }}" method="POST" class="mb-4">
                @csrf
                <div class="btn-group mb-2" role="group">
                    @for ($i = 1; $i <= 5; $i++)
                        <input type="radio" class="btn-check" name="score" id="score{{ $i }}" value="{{ $i }}"
                               {{ $userRating == $i ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-warning" for="score{{ $i }}">{{ $i }} ★</label>
                    @endfor
                </div>
                <div><button class="btn btn-sm btn-warning">Gửi đánh giá</button></div>
                @error('score') <div class="text-danger small">{{ $message }}</div> @enderror
            </form>
        @else
            <p><a href="{{ route('login') }}">Đăng nhập</a> để đánh giá truyện.</p>
        @endauth

        {{-- Bình luận truyện (không gắn chương) --}}
        <h5>Bình luận ({{ $story->comment_count }})</h5>
        @auth
            <form action="{{ route('comments.store', $story) }}" method="POST" class="mb-3">
                @csrf
                <textarea name="content" class="form-control mb-2" rows="3" placeholder="Viết bình luận..." required></textarea>
                <button class="btn btn-sm btn-primary">Gửi bình luận</button>
            </form>
        @else
            <p><a href="{{ route('login') }}">Đăng nhập</a> để bình luận.</p>
        @endauth

        @foreach ($comments as $comment)
            <div class="border-bottom py-2">
                <strong>{{ $comment->user->name }}</strong>
                <span class="text-muted small">{{ $comment->created_at->diffForHumans() }}</span>
                <p class="mb-1">{{ $comment->content }}</p>

                @auth
                    @if ($comment->user_id === auth()->id())
                        <form action="{{ route('comments.destroy', $comment) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Xóa bình luận này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-link btn-sm text-danger p-0">Xóa</button>
                        </form>
                    @endif
                @endauth

                @foreach ($comment->replies as $reply)
                    <div class="ms-4 mt-2 border-start ps-2">
                        <strong>{{ $reply->user->name }}</strong>
                        <span class="text-muted small">{{ $reply->created_at->diffForHumans() }}</span>
                        <p class="mb-0">{{ $reply->content }}</p>
                    </div>
                @endforeach
            </div>
        @endforeach

        {{ $comments->links() }}
    </div>
</div>

@endsection
