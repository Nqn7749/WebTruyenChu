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
            'parent_id' => ['nullable', 'exists:categories,id'],
            // Chặn <> trong tên để không thể chèn thẻ HTML qua tên
            // (tên thể loại được đưa vào thẻ <title> ở trang thể loại).
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string'],
        ];
    }
}
