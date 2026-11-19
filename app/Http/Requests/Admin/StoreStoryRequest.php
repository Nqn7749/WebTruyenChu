<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Chỉ tài khoản đã đăng nhập mới được đăng truyện.
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
            // Chặn <> trong tiêu đề để không thể chèn thẻ HTML qua tiêu đề
            // (phòng thủ thêm cho lỗi XSS ở meta og:title).
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            // description là longText, meta_description là text (tối đa 65.535 ký tự):
            // đặt max để nhập quá dài trả lỗi validation thay vì lỗi 500 của DB.
            'description' => ['nullable', 'string', 'max:1000000'],
            'status' => ['required', Rule::in(['ongoing', 'completed', 'paused'])],
            'categories' => ['required', 'array', 'max:10'],
            'categories.*' => ['exists:categories,id'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['exists:tags,id'],
            'meta_title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'meta_description' => ['nullable', 'string', 'max:60000'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }
}
