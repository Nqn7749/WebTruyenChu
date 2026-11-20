<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\AdminActivityLogger;
use App\Services\StoryDeletionService;
use Illuminate\Http\Request;

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
