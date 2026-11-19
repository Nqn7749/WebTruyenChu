<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Chỉ tài khoản đã đăng nhập mới được bình luận.
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
            'chapter_id' => ['nullable', 'exists:chapters,id'],
            'parent_id'  => ['nullable', 'exists:comments,id'],
            'content'    => ['required', 'string', 'max:2000'],
        ];
    }
}
