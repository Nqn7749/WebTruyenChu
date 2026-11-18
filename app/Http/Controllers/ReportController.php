<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function story(Request $request, Story $story): RedirectResponse
    {
        abort_unless($story->status_publish, 404);
        abort_if($story->user_id === $request->user()->id, 403);

        return $this->store($request, $story);
    }

    public function comment(Request $request, Comment $comment): RedirectResponse
    {
        abort_if($comment->is_hidden || $comment->user_id === $request->user()->id, 404);

        return $this->store($request, $comment);
    }

    private function store(Request $request, Story|Comment $target): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:spam,abuse,copyright,inappropriate,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $created = DB::transaction(function () use ($request, $target, $validated): bool {
            $lockedTarget = $target->newQuery()
                ->lockForUpdate()
                ->findOrFail($target->getKey());

            $exists = Report::query()
                ->where('reporter_id', $request->user()->id)
                ->where('reportable_type', $lockedTarget->getMorphClass())
                ->where('reportable_id', $lockedTarget->getKey())
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                return false;
            }

            $lockedTarget->reports()->create([
                'reporter_id' => $request->user()->id,
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]);

            return true;
        });

        return back()->with('success', $created
            ? 'Đã gửi báo cáo. Cảm ơn bạn đã góp phần xây dựng cộng đồng.'
            : 'Bạn đã gửi báo cáo này trước đó. Báo cáo đang được xem xét.');
    }
}
