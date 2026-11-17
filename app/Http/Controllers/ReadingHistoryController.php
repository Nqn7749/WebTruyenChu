<?php

namespace App\Http\Controllers;

use App\Models\ReadingHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'story_id'       => ['required', 'integer', 'exists:stories,id'],
            'chapter_id'     => [
                'required',
                'integer',
                Rule::exists('chapters', 'id')
                    ->where('story_id', $request->input('story_id'))
                    ->whereNull('deleted_at'),
            ],
            'scroll_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        // A delayed request from the previous chapter must not overwrite the
        // progress of the chapter that is currently active in this history row.
        $updated = ReadingHistory::where('user_id', Auth::id())
            ->where('story_id', $data['story_id'])
            ->where('chapter_id', $data['chapter_id'])
            ->update([
                'scroll_percent' => $data['scroll_percent'],
            ]);

        if ($updated === 0) {
            return response()->json([
                'status' => 'stale',
                'message' => 'Tiến độ đọc đã thay đổi ở chương khác.',
            ], 409);
        }

        return response()->json(['status' => 'ok']);
    }
}