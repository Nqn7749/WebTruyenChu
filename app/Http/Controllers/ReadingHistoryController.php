<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

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
     * Cập nhật % scroll của chương đang đọc (gọi qua AJAX, debounce ở JS).
     * Chỉ cập nhật bản ghi reading_history đã tồn tại (được tạo khi vào ChapterController@show),
     * không tạo mới ở đây để tránh 2 nguồn ghi đè lẫn nhau.
     */
    public function updateProgress(Request $request)
    {
        $data = $request->validate([
            'story_id'       => ['required', 'exists:stories,id'],
            'chapter_id'     => ['required', 'exists:chapters,id'],
            'scroll_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        ReadingHistory::where('user_id', Auth::id())
            ->where('story_id', $data['story_id'])
            ->update([
                'chapter_id'     => $data['chapter_id'],
                'scroll_percent' => $data['scroll_percent'],
            ]);

        return response()->json(['status' => 'ok']);
    }
}