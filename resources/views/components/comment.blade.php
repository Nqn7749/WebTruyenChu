<div class="border-bottom py-3 comment-item" id="comment-{{ $comment->id }}">
    <div class="d-flex justify-content-between">
        <div>
            <strong>{{ $comment->user?->name ?? 'Thành viên đã xóa' }}</strong>
            @if ($comment->chapter)
                <span class="badge bg-secondary ms-2">Chương {{ $comment->chapter->chapter_number }}</span>
            @else
                <span class="badge bg-primary ms-2">Truyện</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">{{ $comment->created_at->diffForHumans() }}</span>
            @auth
                @if ($comment->user_id === Auth::id())
                    <button type="button" class="btn btn-sm btn-outline-danger delete-comment-btn"
                            data-url="{{ route('comments.destroy', $comment) }}"
                            data-comment-id="{{ $comment->id }}" title="Xóa bình luận" aria-label="Xóa bình luận">
                        <i class="bi bi-trash"></i>
                    </button>
                @else
                    <form action="{{ route('reports.comments.store', $comment) }}" method="POST" class="d-flex gap-1">
                        @csrf
                        <select name="reason" class="form-select form-select-sm" aria-label="Lý do báo cáo">
                            <option value="spam">Spam</option>
                            <option value="abuse">Quấy rối / xúc phạm</option>
                            <option value="copyright">Bản quyền</option>
                            <option value="inappropriate">Nội dung không phù hợp</option>
                            <option value="other">Khác</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Báo cáo</button>
                    </form>
                @endif
            @endauth
        </div>
    </div>

    <p class="mb-2 mt-1">{{ $comment->content }}</p>

    @foreach ($comment->replies as $reply)
        <div class="ms-4 border-start ps-3 mt-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $reply->user?->name ?? 'Thành viên đã xóa' }}</strong>
                    <span class="small text-muted">{{ $reply->created_at->diffForHumans() }}</span>
                </div>
                @auth
                    @if ($reply->user_id !== Auth::id())
                        <form action="{{ route('reports.comments.store', $reply) }}" method="POST" class="d-flex gap-1">
                            @csrf
                            <input type="hidden" name="reason" value="other">
                            <button type="submit" class="btn btn-sm btn-link text-muted">Báo cáo</button>
                        </form>
                    @endif
                @endauth
            </div>
            <p class="mb-0">{{ $reply->content }}</p>
        </div>
    @endforeach
</div>
