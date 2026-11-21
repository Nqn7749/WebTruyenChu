<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Đuổi các session khác của user: logoutOtherDevices hash lại
        // mật khẩu mới nên các session cũ (kể cả session bị đánh cắp)
        // không còn khớp hash và bị middleware AuthenticateSession đăng
        // xuất ở request tiếp theo; session hiện tại được giữ lại.
        Auth::logoutOtherDevices($validated['password']);

        // Xoay remember_token: logoutOtherDevices chỉ hash lại mật
        // khẩu, cookie "ghi nhớ đăng nhập" bị đánh cắp vẫn dùng được
        // để lấy session mới. Luồng reset mật khẩu đã xoay token này
        // (round 7 M).
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();

        // Tạo lại session id sau khi đổi mật khẩu: kẻ đang giữ session
        // id cũ không còn dùng được nữa (round 7 L).
        $request->session()->regenerate();

        return back()->with('status', 'password-updated');
    }
}
