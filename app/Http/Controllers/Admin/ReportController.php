<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Story;
use App\Services\AdminActivityLogger;
use App\Services\StoryDeletionService;
use App\Services\StoryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate([
            'status' => ['nullable', 'in:pending,reviewed,resolved,dismissed'],
        ])['status'] ?? 'pending';

        $reports = Report::query()
            ->with(['reporter', 'reportable', 'reviewer'])
            ->where('status', $status)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.index', compact('reports', 'status'));
    }

    public function update(
        Request $request,
        Report $report,
        StoryDeletionService $deletion,
        AdminActivityLogger $logger
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', 'in:none,hide_comment,delete_story'],
            'resolution_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $report, $data, $deletion, $logger) {
            $lockedReport = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless($lockedReport->status === 'pending', 409, 'Báo cáo đã được xử lý.');

            $target = $lockedReport->reportable;

            if ($data['action'] === 'hide_comment') {
                if (! ($target instanceof Comment)) {
                    throw ValidationException::withMessages([
                        'action' => 'Chỉ có thể ẩn nội dung bình luận bằng thao tác này.',
                    ]);
                }

                if ($target->is_hidden === false) {
                    // is_hidden không nằm trong $fillable của Comment nên gán riêng ở đây.
                    $target->forceFill(['is_hidden' => true])->save();

                    // Ẩn comment cha cũng ẩn các reply của nó trên giao
                    // diện nên số đếm phải trừ theo cả reply (N-L3),
                    // và không để âm (NM-4).
                    $amount = 1 + $target->replies()->where('is_hidden', false)->count();

                    StoryStatsService::decrementCommentCount('stories', $target->story_id, $amount);

                    if ($target->chapter_id) {
                        StoryStatsService::decrementCommentCount('chapters', $target->chapter_id, $amount);
                    }
                }
            }

            if ($data['action'] === 'delete_story') {
                if (! ($target instanceof Story)) {
                    throw ValidationException::withMessages([
                        'action' => 'Chỉ có thể xóa truyện bằng thao tác này.',
                    ]);
                }

                $deleted = $deletion->delete(
                    $target,
                    $data['resolution_note'],
                    $request->user()->id
                );

                abort_unless($deleted, 409, 'Truyện không còn tồn tại hoặc đã bị xóa.');
            }

            // Các trường xử lý không nằm trong $fillable của Report nên gán riêng ở đây.
            $lockedReport->forceFill([
                'status' => $data['action'] === 'none' ? 'dismissed' : 'resolved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'resolution_note' => $data['resolution_note'],
            ])->save();

            $logger->record(
                $request,
                'report.'.$lockedReport->status,
                $target,
                'Xử lý báo cáo #'.$lockedReport->id.': '.$data['resolution_note']
            );
        });

        return back()->with('success', 'Đã cập nhật kết quả xử lý báo cáo.');
    }
}
