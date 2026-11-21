<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'], // chấp nhận cả email lẫn username
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->input('email');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Ghi thêm vào bucket toàn cục theo tài khoản (không gắn IP)
            // để xoay IP không bypass được ngân sách theo tài khoản.
            // Bucket này chỉ chặn lần thử SAI: mật khẩu đúng vẫn phải
            // đăng nhập được dù bucket đã đầy, nếu không kẻ tấn công
            // cố tình thử sai 20 lần sẽ khóa luôn tài khoản nạn nhân
            // (kể cả admin) — round 7.
            $globalKey = $this->globalThrottleKey();

            if ($globalKey !== null) {
                RateLimiter::hit($globalKey, 3600);

                // attempts() > 20 giữ đúng ranh giới cũ: lần sai thứ
                // 20 vẫn trả auth.failed, từ lần sai thứ 21 mới báo
                // throttle.
                if (RateLimiter::attempts($globalKey) > 20) {
                    $this->throwRateLimited($globalKey);
                }
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Tài khoản bị khóa: đăng xuất ngay. Trả về thông báo chung chung
        // như khi sai mật khẩu để không lộ là mật khẩu đã đúng
        // (kẻ tấn công đoán trúng mật khẩu của tài khoản bị khóa sẽ
        // biết mình đoán đúng) (L1).
        if (! Auth::user()->status) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $globalKey = $this->globalThrottleKey();

        if ($globalKey !== null) {
            RateLimiter::clear($globalKey);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * Chỉ kiểm tra throttle theo user+IP (5 lần/phút) trước khi thử
     * đăng nhập. Bucket toàn cục theo tài khoản (20 lần/giờ) KHÔNG
     * kiểm tra ở đây nữa: nó chỉ được chặn sau một lần thử SAI
     * (xem authenticate()), để mật khẩu đúng vẫn đăng nhập được dù
     * bucket đã đầy — nếu chặn trước như trước đây thì kẻ tấn công
     * thử sai 20 lần là khóa luôn tài khoản nạn nhân (round 7).
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            $this->throwRateLimited($this->throttleKey());
        }
    }

    /**
     * Ném lỗi khóa đăng nhập tạm thời cho một key throttle.
     *
     * @throws ValidationException
     */
    private function throwRateLimited(string $key): void
    {
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Tài khoản được đăng nhập (tìm theo email hoặc username), hoặc null
     * nếu không tồn tại. Kết quả được nhớ lại trong request để các key
     * throttle không phải truy vấn nhiều lần.
     */
    private ?User $cachedLoginUser = null;

    private bool $loginUserLoaded = false;

    private function loginUser(): ?User
    {
        if (! $this->loginUserLoaded) {
            $login = (string) $this->string('email');

            $this->cachedLoginUser = User::query()
                ->where('email', $login)
                ->orWhere('username', $login)
                ->first(['id']);
            $this->loginUserLoaded = true;
        }

        return $this->cachedLoginUser;
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $login = (string) $this->string('email');

        // Cùng một tài khoản đăng nhập bằng email hay username đều chung
        // một ngân sách 5 lần sai: trước đây mỗi cách nhập là một key
        // riêng nên kẻ tấn công có gấp đôi số lần thử cho cùng một tài
        // khoản (P-M5). Không tìm thấy tài khoản thì giữ nguyên key cũ
        // để không đổi hành vi với identity chưa tồn tại.
        $user = $this->loginUser();

        if ($user) {
            return 'user:'.$user->id.'|'.$this->ip();
        }

        return Str::transliterate(Str::lower($login).'|'.$this->ip());
    }

    /**
     * Key bucket toàn cục theo tài khoản (không gắn IP), hoặc null nếu
     * tài khoản không tồn tại.
     */
    public function globalThrottleKey(): ?string
    {
        $user = $this->loginUser();

        return $user ? 'login-user:'.$user->id : null;
    }
}
