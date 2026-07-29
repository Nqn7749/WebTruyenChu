<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'parent_id'   => [
                'nullable',
                'exists:categories,id',
                Rule::notIn([$this->route('category')->id]),
            ],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
