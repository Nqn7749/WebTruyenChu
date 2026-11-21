<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // Cột users.name là varchar(100): max:255 cho qua rồi MySQL
            // báo 500 (NM-2).
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique(User::class, 'username'),
            ],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $userRoleId = Role::where('slug', 'user')->value('id');

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // role_id không nằm trong $fillable của User nên gán riêng ở đây.
        $user->forceFill(['role_id' => $userRoleId])->save();

        event(new Registered($user));

        Auth::login($user);

        // Tạo lại session id sau khi đăng nhập: nếu không, kẻ tấn công
        // gài sẵn session id cho nạn nhân (session fixation) rồi chờ nạn
        // nhân đăng ký/đăng nhập là chiếm luôn phiên đã xác thực (M5).
        $request->session()->regenerate();

        return redirect(route('home', absolute: false));
    }
}
