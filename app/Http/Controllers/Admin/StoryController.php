<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkChapterNotifications;
use App\Models\Story;
use App\Services\AdminActivityLogger;
use App\Services\HomeCacheService;
use App\Services\StoryDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoryController extends Controller
{
    public function index()
    {
        $stories = Story::query()
            ->with('user')
            ->withCount('chapters')
            ->latest('id')
            ->paginate(15);

        return view('admin.stories.index', compact('stories'));
    }

    /**
     * Công khai lại truyện đã bị gỡ, hoặc gỡ truyện đang công khai khỏi website.
     * Truyện mới do thành viên đăng được công khai ngay (tác giả tự đăng),
     * không qua hàng chờ duyệt.
     */
    public function togglePublish(Request $request, Story $story, AdminActivityLogger $logger)
    {
        $published = ! $story->status_publish;

        $story->forceFill(['status_publish' => $published])->save();

        if ($published) {
            // Các chương đã công khai trong lúc truyện bị gỡ chưa từng gửi
            // thông báo: gửi bù cho từng chương đã công khai. Job tự kiểm
            // tra lại trạng thái lúc chạy và bỏ qua người đã nhận rồi (H5).
            // Gửi theo lô 100 chương/job thay vì 1 job/chương: truyện 1000
            // chương re-publish trước đây tạo 1000 job (N-L5).
            $story->chapters()
                ->where('status', true)
                ->chunkById(100, function ($chapters) {
                    SendBulkChapterNotifications::dispatch($chapters->pluck('id')->all());
                });
        }

        $logger->record(
            $request,
            $published ? 'story.published' : 'story.unpublished',
            $story,
            ($published ? 'Công khai truyện "' : 'Gỡ công khai truyện "').$story->title.'"'
        );

        return back()->with('success', $published
            ? 'Đã công khai truyện.'
            : 'Đã gỡ truyện khỏi website.');
    }

    /**
     * Bật/tắt một cờ hiển thị của truyện (N-M6): is_hot, is_featured,
     * is_recommended. Ba cờ này trước đây chỉ set false lúc đăng truyện
     * và set ngẫu nhiên trong seeder, không có chỗ nào bật lên nên mục
     * "Truyện hot" / "Truyện đề cử" ở trang chủ luôn trống với dữ liệu thật.
     *
     * Làm bằng nút bật/tắt ngay trên trang quản lý thay vì hồi sinh form
     * sửa truyện của admin: form đó là dead code (route admin.stories chỉ
     * có index/destroy, không có edit/update) nên đưa cờ vào form sẽ
     * không tới được tay admin. Các cờ không nằm trong $fillable nên
     * gán bằng forceFill(), giống cách Writer/StoryController set cờ lúc
     * tạo truyện. Đổi cờ xong làm mới cache trang chủ để mục Hot/Đề cử
     * cập nhật ngay thay vì chờ hết 10 phút cache (M13).
     */
    public function toggleFlag(Request $request, Story $story, AdminActivityLogger $logger)
    {
        $validated = $request->validate([
            'flag' => ['required', 'string', Rule::in(['is_hot', 'is_featured', 'is_recommended'])],
        ]);

        $flag = $validated['flag'];

        $labels = [
            'is_hot' => 'Truyện hot',
            'is_featured' => 'Nổi bật',
            'is_recommended' => 'Đề cử',
        ];

        $enabled = ! $story->{$flag};

        $story->forceFill([$flag => $enabled])->save();

        app(HomeCacheService::class)->invalidate();

        $logger->record(
            $request,
            $enabled ? 'story.flag_enabled' : 'story.flag_disabled',
            $story,
            ($enabled ? 'Bật "' : 'Tắt "').$labels[$flag].'" cho truyện "'.$story->title.'"'
        );

        return back()->with('success', ($enabled ? 'Đã bật "' : 'Đã tắt "').$labels[$flag].'" cho truyện.');
    }

    public function destroy(Request $request, Story $story, StoryDeletionService $deletion, AdminActivityLogger $logger)
    {
        $validated = $request->validate([
            'deletion_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'deletion_reason.required' => 'Vui lòng nhập lý do xóa truyện.',
            'deletion_reason.min' => 'Lý do xóa phải có ít nhất 10 ký tự.',
            'deletion_reason.max' => 'Lý do xóa không được vượt quá 1000 ký tự.',
        ]);

        $deleted = $deletion->delete($story, $validated['deletion_reason'], $request->user()->id);
        abort_unless($deleted, 404);

        $logger->record($request, 'story.deleted', $story, 'Xóa truyện "'.$story->title.'": '.$validated['deletion_reason']);

        return redirect()->route('admin.stories.index')->with('success', 'Đã xóa truyện và lưu lý do xóa.');
    }
}
