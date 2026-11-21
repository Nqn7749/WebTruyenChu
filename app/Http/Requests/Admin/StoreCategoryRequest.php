<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Chỉ admin mới được quản lý thể loại (NL-11): trước đây trả
        // true mù, chỉ nhờ lớp middleware role:admin ở ngoài.
        return $this->user()?->role?->slug === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'exists:categories,id'],
            // Chặn <> trong tên để không thể chèn thẻ HTML qua tên
            // (tên thể loại được đưa vào thẻ <title> ở trang thể loại).
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            // Cột description là TEXT (tối đa 65.535 byte): 16.000 ký tự là
            // mức an toàn kể cả khi toàn emoji (4 byte/ký tự, ~64.000
            // byte), quá dài trả lỗi validation thay vì lỗi 500 của DB.
            'description' => ['nullable', 'string', 'max:16000'],
        ];
    }
}
