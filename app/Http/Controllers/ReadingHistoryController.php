<?php

namespace App\Http\Controllers;

use App\Models\ReadingHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReadingHistoryController extends Controller
{
    public function index()
    {
        $histories = Auth::user()
            ->readingHistories()
            ->with(['story', 'chapter'])
            ->latest('read_at')
            ->paginate(20);

        return view('reading-history.index', compact('histories'));
    }

    /**
     * Cập nhật % scroll của chương đang đọc (AJAX, debounce ở JS).
     * Chỉ update bản ghi đã tồn tại (được tạo ở ChapterController@show).
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'story_id' => ['required', 'integer', 'exists:stories,id'],
            'chapter_id' => [
                'required',
                'integer',
                Rule::exists('chapters', 'id')
                    ->where('story_id', $request->input('story_id'))
                    ->whereNull('deleted_at'),
            ],
            'scroll_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        // Lock the current history row so delayed requests from a previous
        // chapter cannot overwrite progress after the reader has moved on.
        $updated = DB::transaction(function () use ($data) {
            $history = ReadingHistory::where('user_id', Auth::id())
                ->where('story_id', $data['story_id'])
                ->lockForUpdate()
                ->first();

            if (! $history || (int) $history->chapter_id !== (int) $data['chapter_id']) {
                return false;
            }

            $history->scroll_percent = $data['scroll_percent'];
            $history->save();

            return true;
        });

        if (! $updated) {
            return response()->json([
                'status' => 'stale',
                'message' => 'Tiến độ đọc đã thay đổi ở chương khác.',
            ], 409);
        }

        return response()->json(['status' => 'ok']);
    }
}
