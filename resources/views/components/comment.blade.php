<div
    class="border-bottom py-3 comment-item"
    id="comment-{{ $comment->id }}"
>
    <div class="d-flex justify-content-between">

        {{-- Thông tin người bình luận --}}
        <div>
            <strong>
                {{ $comment->user->name }}
            </strong>

            @if ($comment->chapter)

                <span class="badge bg-secondary ms-2">
                    Chương {{ $comment->chapter->chapter_number }}
                </span>

            @else

                <span class="badge bg-primary ms-2">
                    Truyện
                </span>

            @endif
        </div>


        {{-- Thời gian + nút xóa --}}
        <div class="d-flex align-items-center gap-2">

            <span class="small text-muted">
                {{ $comment->created_at->diffForHumans() }}
            </span>

            @auth

                @if ($comment->user_id === Auth::id())

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger delete-comment-btn"
                        data-url="{{ route('comments.destroy', $comment) }}"
                        data-comment-id="{{ $comment->id }}"
                        title="Xóa bình luận"
                        aria-label="Xóa bình luận"
                    >
                        <i class="bi bi-trash"></i>
                    </button>

                @endif

            @endauth

        </div>

    </div>


    {{-- Nội dung comment --}}
    <p class="mb-2 mt-1">
        {{ $comment->content }}
    </p>


    {{-- Replies --}}
    @foreach ($comment->replies as $reply)

        <div class="ms-4 border-start ps-3 mt-2">

            <strong>
                {{ $reply->user->name }}
            </strong>

            <span class="small text-muted">
                {{ $reply->created_at->diffForHumans() }}
            </span>

            <p class="mb-0">
                {{ $reply->content }}
            </p>

        </div>

    @endforeach

</div>