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
            'title'            => ['required', 'string', 'max:255'],
            'author_name'      => ['nullable', 'string', 'max:255'],
            'cover_image'      => ['nullable', 'image', 'max:2048'],
            'description'      => ['nullable', 'string'],
            'status'           => ['required', Rule::in(['ongoing', 'completed', 'paused'])],
            'categories'       => ['required', 'array'],
            'categories.*'     => ['exists:categories,id'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
