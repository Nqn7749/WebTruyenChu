<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreChapterRequest extends FormRequest
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
        $storyId = $this->route('story')->id;

        return [
            'chapter_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('chapters')->where(
                    fn ($q) => $q->where('story_id', $storyId)->whereNull('deleted_at')
                ),
            ],
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status'  => ['nullable', 'boolean'],
        ];
    }
}
