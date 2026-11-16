<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\StoryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, StoryStatsService $stats): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // withTrashed(): truyện đã soft-delete vẫn còn dòng trong DB và vẫn
        // bị FK restrictOnDelete chặn, nếu bỏ qua sẽ gây QueryException.
        if ($user->stories()->withTrashed()->exists()) {
            return back()->withErrors([
                'password' => 'Bạn đang có truyện đăng trên hệ thống. Vui lòng liên hệ quản trị viên để chuyển quyền tác giả hoặc xóa truyện trước khi xóa tài khoản.',
            ], 'userDeletion');
        }

        // Gom các truyện/chương bị ảnh hưởng TRƯỚC khi xóa, vì DB sẽ cascade
        // xóa comment, rating, favorite của user này.
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

        Auth::logout();

        DB::transaction(function () use ($user, $stats, $storyIds, $chapterIds) {
            $user->delete();

            $stats->refreshStories($storyIds);
            $stats->refreshChapterComments($chapterIds);
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}