<div class="border-bottom py-3 comment-item" id="comment-{{ $comment->id }}">
    <div class="d-flex justify-content-between align-items-start">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted fw-bold small flex-shrink-0" style="width: 34px; height: 34px; border: 1px solid var(--sand-border);">
                {{ mb_substr($comment->user?->name ?? 'U', 0, 1, 'UTF-8') }}
            </div>
            <div>
                <strong>{{ $comment->user?->name ?? 'Thành viên đã xóa' }}</strong>
                @if ($comment->chapter)
                    <span class="badge bg-secondary ms-1">Chương {{ $comment->chapter->chapter_number }}</span>
                @else
                    <span class="badge ms-1" style="background: var(--jade-tint); color: var(--jade-dark);">Truyện</span>
                @endif
                <div class="small text-muted">{{ $comment->created_at->diffForHumans() }}</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @auth
                @if ($comment->user_id === Auth::id())
                    <button type="button" class="btn btn-sm btn-outline-danger delete-comment-btn"
                            data-url="{{ route('comments.destroy', $comment) }}"
                            data-comment-id="{{ $comment->id }}" title="Xóa bình luận" aria-label="Xóa bình luận">
                        <i class="bi bi-trash"></i>
                    </button>
                @else
                    <div class="dropdown">
                        <button class="btn btn-sm btn-link text-muted p-1 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Tùy chọn" aria-label="Tùy chọn">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm p-2" style="min-width: 220px;">
                            <li>
                                <form action="{{ route('reports.comments.store', $comment) }}" method="POST">
                                    @csrf
                                    <label class="form-label small text-muted mb-1 fw-semibold">Báo cáo bình luận:</label>
                                    <select name="reason" class="form-select form-select-sm mb-2" aria-label="Lý do báo cáo" required>
                                        <option value="spam">Spam / Quảng cáo</option>
                                        <option value="abuse">Quấy rối / Xúc phạm</option>
                                        <option value="copyright">Vi phạm bản quyền</option>
                                        <option value="inappropriate">Nội dung không phù hợp</option>
                                        <option value="other">Khác</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Gửi báo cáo</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endif
            @endauth
        </div>
    </div>

    <p class="mb-2 mt-2 ps-1">{{ $comment->content }}</p>

    @foreach ($comment->replies as $reply)
        <div class="ms-4 border-start ps-3 mt-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $reply->user?->name ?? 'Thành viên đã xóa' }}</strong>
                    <span class="small text-muted ms-1">{{ $reply->created_at->diffForHumans() }}</span>
                </div>
                @auth
                    @if ($reply->user_id !== Auth::id())
                        <form action="{{ route('reports.comments.store', $reply) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="reason" value="other">
                            <button type="submit" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" title="Báo cáo vi phạm" onclick="return confirm('Bạn muốn báo cáo phản hồi này?')">
                                <i class="bi bi-flag"></i> <span class="small">Báo cáo</span>
                            </button>
                        </form>
                    @endif
                @endauth
            </div>
            <p class="mb-0 mt-1">{{ $reply->content }}</p>
        </div>
    @endforeach
</div>
