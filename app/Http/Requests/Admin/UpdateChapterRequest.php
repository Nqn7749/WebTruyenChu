<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChapterRequest extends FormRequest
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
        $chapter = $this->route('chapter');

        return [
            'chapter_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('chapters')
                    ->where(fn ($q) => $q->where('story_id', $chapter->story_id))
                    ->ignore($chapter->id),
            ],
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status'  => ['nullable', 'boolean'],
        ];
    }
}
