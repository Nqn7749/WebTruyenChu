<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Danh sách thông báo.
     */
    public function index(): View
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Đánh dấu một notification là đã đọc.
     */
    public function markAsRead(string $id): RedirectResponse
    {
        $notification = Auth::user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if ($url) {
            return redirect()->to($url);
        }

        return back();
    }

    /**
     * Đánh dấu tất cả notification là đã đọc.
     */
    public function markAllAsRead(): RedirectResponse
    {
        Auth::user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return back()->with(
            'success',
            'Đã đánh dấu tất cả là đã đọc.'
        );
    }

    public function unread(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $notifications = $user->unreadNotifications()
            ->latest()
            ->take(8)
            ->get();

        return response()->json([
            'success' => true,

            'unread_count' => $user
                ->unreadNotifications()
                ->count(),

            'notifications' => $notifications
                ->map(function ($notification) {

                    return [
                        'id' => $notification->id,

                        'message' =>
                            $notification->data['message']
                            ?? 'Bạn có thông báo mới.',

                        'created_at' =>
                            $notification
                                ->created_at
                                ->diffForHumans(),

                        'url' =>
                            $notification
                                ->data['url']
                                ?? null,

                        'mark_read_url' =>
                            route(
                                'notifications.mark-read',
                                $notification->id
                            ),
                    ];
                })
                ->values(),
        ]);
    }
}