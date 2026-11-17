<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Danh sách thông báo của người dùng hiện tại.
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
     * Đánh dấu một thông báo đã đọc và chỉ điều hướng tới URL nội bộ.
     */
    public function markAsRead(string $id): RedirectResponse
    {
        $notification = Auth::user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        $url = $this->safeInternalUrl($notification->data['url'] ?? null);

        return $url
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    /**
     * Đánh dấu tất cả thông báo chưa đọc của người dùng hiện tại.
     */
    public function markAllAsRead(): RedirectResponse
    {
        Auth::user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc.');
    }

    /**
     * Dữ liệu tối giản cho chuông thông báo.
     */
    public function unread(): JsonResponse
    {
        $user = Auth::user();

        $unreadNotifications = $user->unreadNotifications();
        $unreadCount = (clone $unreadNotifications)->count();

        $notifications = $unreadNotifications
            ->latest()
            ->take(8)
            ->get(['id', 'data', 'created_at'])
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'message' => $notification->data['message'] ?? 'Bạn có thông báo mới.',
                'created_at' => $notification->created_at->diffForHumans(),
                'mark_read_url' => route('notifications.mark-read', $notification->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Chỉ chấp nhận đường dẫn tương đối nội bộ hoặc URL cùng host hiện tại.
     * Không tin cậy URL lưu trong payload notification.
     */
    private function safeInternalUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if (! isset($parts['host'])) {
            $path = $parts['path'] ?? '';

            if (
                ! str_starts_with($path, '/')
                || str_starts_with($path, '//')
                || str_contains($path, chr(92))
                || preg_match('/[\\x00-\\x1F\\x7F]/', $path)
            ) {
                return null;
            }

            return $this->appendUrlSuffix($path, $parts);
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host']);

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ! hash_equals(strtolower(request()->getHost()), $host)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        if (
            ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, chr(92))
            || preg_match('/[\\x00-\\x1F\\x7F]/', $path)
        ) {
            return null;
        }

        return $this->appendUrlSuffix($path, $parts);
    }

    /**
     * Giữ lại query string và fragment sau khi loại bỏ host.
     *
     * @param  array<string, mixed>  $parts
     */
    private function appendUrlSuffix(string $path, array $parts): string
    {
        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $path .= '#'.$parts['fragment'];
        }

        return $path;
    }
}
