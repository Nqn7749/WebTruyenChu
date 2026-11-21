<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Cột users.name là varchar(100): max:255 cho qua rồi MySQL
            // báo 500 (NM-2).
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        // Đổi email là hành động nhạy cảm: chỉ cần session bị đánh cắp
        // là kẻ tấn công đổi email rồi dùng "quên mật khẩu" để chiếm
        // luôn tài khoản (round 7 H1). Yêu cầu nhập lại mật khẩu hiện
        // tại khi email thực sự đổi; đổi tên hay giữ nguyên email thì
        // không cần. So sánh không phân biệt hoa/thường vì email đã
        // chuẩn hóa lowercase ở tầng validation.
        $submittedEmail = $this->input('email');
        $currentEmail = $this->user()?->email;

        if (is_string($submittedEmail) && is_string($currentEmail)
            && mb_strtolower($submittedEmail) !== mb_strtolower($currentEmail)
        ) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        return $rules;
    }
}
