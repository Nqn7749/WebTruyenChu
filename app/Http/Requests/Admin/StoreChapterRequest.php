<?php

namespace App\Http\Requests\Admin;

use App\Models\Story;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Số chương không được trùng trong cùng một truyện (DB đã bỏ
            // unique này ở migration 2026-11-16, nên chặn ở validation).
            'chapter_number' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'boolean'],
        ];

        if ($this->storyId() !== null) {
            $rules['chapter_number'][] = Rule::unique('chapters', 'chapter_number')
                ->where('story_id', $this->storyId());
        }

        return $rules;
    }

    private function storyId(): ?int
    {
        $story = $this->route('story');

        if ($story instanceof Story) {
            return $story->id;
        }

        return is_numeric($story) ? (int) $story : null;
    }
}
