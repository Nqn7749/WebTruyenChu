<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
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
            // Chặn <> trong tên để không thể chèn thẻ HTML qua tên
            // (cùng quy tắc với tên thể loại, phòng thủ cho nơi hiển thị tên).
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/', 'unique:tags,name'],
        ];
    }
}
