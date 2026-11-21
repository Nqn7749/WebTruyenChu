<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Chỉ tài khoản đã đăng nhập mới được đánh giá (NL-11): route đã
        // nằm sau auth nhưng authorize() trả true mù thì request có thể
        // bị reuse ở route khác mà không có lớp bảo vệ nào.
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
