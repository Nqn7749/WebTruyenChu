<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AdminAccountProtectionService;
use App\Services\StoryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(
        Request $request,
        StoryStatsService $stats,
        AdminAccountProtectionService $adminProtection
    ): RedirectResponse {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->stories()->withTrashed()->exists()) {
            return back()->withErrors([
                'password' => 'Bạn đang có truyện đăng trên hệ thống. Vui lòng liên hệ quản trị viên để chuyển quyền tác giả hoặc xóa truyện trước khi xóa tài khoản.',
            ], 'userDeletion');
        }

        DB::transaction(function () use ($user, $stats, $adminProtection) {
            // Thu thập danh sách truyện/chương cần đếm lại TRONG
            // transaction, ngay trước khi xóa: pluck trước transaction
            // có thể bỏ sót bình luận/đánh giá/yêu thích phát sinh đúng
            // trong khoảng đó, truyện của chúng không được đếm lại (F8).
            $storyIds = collect()
                ->merge($user->comments()->withTrashed()->pluck('story_id'))
                ->merge($user->ratings()->pluck('story_id'))
                ->merge($user->favorites()->pluck('story_id'))
                ->unique()
                ->values();

            $chapterIds = $user->comments()
                ->withTrashed()
                ->whereNotNull('chapter_id')
                ->pluck('chapter_id')
                ->unique()
                ->values();

            $adminProtection->assertCanDelete($user);
            DB::table($user->getTable())->where($user->getKeyName(), $user->getKey())->delete();

            // Dọn notification của tài khoản: bảng notifications là
            // polymorphic (notifiable_type/notifiable_id) nên không có
            // FK cascade, không xóa ở đây thì notification chưa đọc của
            // tài khoản đã xóa sẽ ở lại vĩnh viễn (C4/F9).
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->delete();

            $stats->refreshStories($storyIds);
            $stats->refreshChapterComments($chapterIds);
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
