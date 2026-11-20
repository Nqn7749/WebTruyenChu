<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
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
        $tag = $this->route('tag');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Chặn <> trong tên để không thể chèn thẻ HTML qua tên
                // (cùng quy tắc với tên thể loại, phòng thủ cho nơi hiển thị tên).
                'not_regex:/[<>]/',
                Rule::unique('tags', 'name')->ignore($tag->id),
            ],
        ];
    }
}
