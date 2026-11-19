<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChapterRequest extends FormRequest
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
        $chapter = $this->route('chapter');
        $chapterId = $chapter instanceof \App\Models\Chapter ? $chapter->id : (is_numeric($chapter) ? (int) $chapter : null);
        $storyId = $chapter instanceof \App\Models\Chapter ? $chapter->story_id : null;

        $numberRules = ['required', 'integer', 'min:1'];

        if ($storyId !== null) {
            $numberRules[] = \Illuminate\Validation\Rule::unique('chapters', 'chapter_number')
                ->where('story_id', $storyId)
                ->ignore($chapterId);
        }

        return [
            'chapter_number' => $numberRules,
            'title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
