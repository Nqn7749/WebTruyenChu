<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Services\StoryStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Store a new comment.
     */
    public function store(
        StoreCommentRequest $request,
        Story $story
    ): JsonResponse|RedirectResponse {
        abort_unless($story->status_publish, 404);

        $data = $request->validated();

        $data['story_id'] = $story->id;

        /*
        |--------------------------------------------------------------------------
        | Validate chapter
        |--------------------------------------------------------------------------
        */

        if (! empty($data['chapter_id'])) {
            $chapter = Chapter::query()
                ->whereKey($data['chapter_id'])
                ->where('story_id', $story->id)
                ->where('status', true)
                ->firstOrFail();
        }

        /*
        |--------------------------------------------------------------------------
        | Validate parent comment
        |--------------------------------------------------------------------------
        */

        if (! empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);

            abort_unless(
                $parent->story_id === $story->id && ! $parent->is_hidden,
                404
            );

            // The discussion UI supports one reply level only.
            abort_unless(
                $parent->parent_id === null,
                422,
                'Không thể trả lời một bình luận trả lời khác.'
            );

            if ($parent->chapter_id) {
                abort_unless(
                    Chapter::query()
                        ->whereKey($parent->chapter_id)
                        ->where('story_id', $story->id)
                        ->where('status', true)
                        ->exists(),
                    404
                );
            }

            // Reply luôn thuộc cùng chapter với comment cha.
            $data['chapter_id'] = $parent->chapter_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Chống spam: một user không bình luận 2 lần trong 30 giây (tính
        | theo user, không phân biệt truyện để đổi truyện khác không còn
        | bypass được — C2) và không gửi trùng nội dung trong 10 phút
        | gần nhất (P-M6). Khóa theo user trong suốt quá trình
        | kiểm tra + tạo bình luận để hai request đăng cùng lúc không
        | cùng đọc được dữ liệu cũ rồi cùng insert (F4) — khóa theo
        | user (không theo truyện) vì cooldown 30 giây đã tính toàn
        | cục theo user (C2).
        |--------------------------------------------------------------------------
        */

        $lock = Cache::lock('comment-post-'.Auth::id(), 10);

        abort_unless($lock->acquire(), 429, 'Bạn đang gửi bình luận, thử lại sau ít giây.');

        try {
            $latest = Comment::query()
                ->where('user_id', Auth::id())
                ->latest('id')
                ->first();

            abort_if($latest && $latest->created_at->gt(now()->subSeconds(30)), 429, 'Bạn đang bình luận quá nhanh, thử lại sau ít giây.');

            // So trùng sau khi chuẩn hóa (trim, gộp khoảng trắng, hạ
            // chữ hoa): sửa khoảng trắng/hoa-thường không còn qua mặt
            // được, và so với mọi bình luận trong 10 phút gần nhất chứ
            // không chỉ bình luận mới nhất (C1).
            $normalized = $this->normalizeCommentContent($data['content']);

            $recent = Comment::query()
                ->where('user_id', Auth::id())
                ->where('story_id', $story->id)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest('id')
                ->take(20)
                ->get(['content']);

            foreach ($recent as $comment) {
                abort_if($this->normalizeCommentContent($comment->content) === $normalized, 422, 'Bạn vừa gửi bình luận giống hệt nội dung này.');
            }

            /*
            |--------------------------------------------------------------------------
            | Create comment
            |--------------------------------------------------------------------------
            */

            $comment = DB::transaction(function () use ($data, $story) {

                // Chương được validate TRƯỚC transaction, nhưng có thể
                // bị xóa mềm ngay sau đó (F5): đọc lại trong transaction,
                // khóa dòng, kiểm tra lại trạng thái trước khi insert.
                if (! empty($data['chapter_id'])) {
                    $chapter = Chapter::query()
                        ->lockForUpdate()
                        ->findOrFail($data['chapter_id']);

                    abort_unless(
                        $chapter->story_id === $story->id && $chapter->status,
                        404
                    );
                }

                // user_id không nằm trong $fillable của Comment nên không
                // đưa vào create(): gán trực tiếp rồi save một lần. Trước
                // đây create() insert trước khi user_id được gán nên cột
                // user_id NOT NULL bị lỗi ngay ở câu INSERT (fix1).
                $comment = new Comment($data);
                $comment->user_id = Auth::id();
                $comment->save();

                // Tăng số comment của story/chương. Bình luận mới không phải
                // cập nhật nội dung nên đóng băng updated_at để sitemap
                // không tưởng truyện mới cập nhật (S-M1).
                $story->timestamps = false;
                $story->increment('comment_count');
                $story->timestamps = true;

                if ($comment->chapter_id) {
                    $comment->chapter->timestamps = false;
                    $comment->chapter->increment('comment_count');
                    $comment->chapter->timestamps = true;
                }

                return $comment;
            });
        } finally {
            $lock->release();
        }

        /*
        |--------------------------------------------------------------------------
        | Load relationships
        |--------------------------------------------------------------------------
        */

        $comment->load([
            'user',
            'chapter',
            'replies.user',
        ]);

        // Gửi form thường (không AJAX) -> quay lại trang kèm flash message
        if (! $request->expectsJson()) {
            return back()->with('success', 'Đã gửi bình luận.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bình luận.',
            'html' => view('components.comment', ['comment' => $comment])->render(),
        ]);
    }

    /**
     * Chuẩn hóa nội dung bình luận để so trùng: bỏ khoảng trắng đầu/
     * cuối, gộp khoảng trắng thừa thành một, hạ chữ hoa (C1).
     */
    private function normalizeCommentContent(string $content): string
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $content));

        return mb_strtolower($collapsed);
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $comment->user_id === Auth::id(),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($comment) {

            // Khóa comment để hai lần xóa đồng thời không trừ số đếm
            // hai lần: lần thứ hai chờ xong sẽ không tìm thấy comment
            // nữa (đã xóa mềm) mà nhận 404 thay vì trừ trùng (C-L1).
            $locked = Comment::query()->lockForUpdate()->findOrFail($comment->id);

            $story = $locked->story;
            $chapter = $locked->chapter;

            // Chỉ trừ đúng phần đang được đếm: reply của cha đã ẩn/xóa
            // hoặc comment của chương đã xóa mềm vốn không được đếm
            // nên xóa chúng không đổi số đếm (C-L2). Trừ có chặn ở 0
            // khi số đếm đã lệch trước đó (NM-4).
            $amount = StoryStatsService::countedCommentAmount($locked);

            if ($amount > 0) {
                if ($story) {
                    StoryStatsService::decrementCommentCount('stories', $story->id, $amount);
                }

                if ($chapter) {
                    StoryStatsService::decrementCommentCount('chapters', $chapter->id, $amount);
                }
            }

            $locked->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận.',
        ]);
    }
}
