<?php

namespace App\Http\Requests\Admin;

use App\Models\Chapter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Request này dùng chung cho cả admin và writer (NL-11): admin
        // được sửa chương bất kỳ, writer chỉ sửa chương của truyện mình.
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->role?->slug === 'admin') {
            return true;
        }

        $chapter = $this->route('chapter');

        return $chapter instanceof Chapter && $chapter->story?->user_id === $user->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $chapter = $this->route('chapter');
        $chapterId = $chapter instanceof Chapter ? $chapter->id : (is_numeric($chapter) ? (int) $chapter : null);
        $storyId = $chapter instanceof Chapter ? $chapter->story_id : null;

        $numberRules = ['required', 'integer', 'min:1', 'max:100000'];

        if ($storyId !== null) {
            $numberRules[] = Rule::unique('chapters', 'chapter_number')
                ->where('story_id', $storyId)
                ->whereNull('deleted_at')
                ->ignore($chapterId);
        }

        return [
            'chapter_number' => $numberRules,
            'title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            // Cột content là longText nhưng vẫn cần max để tránh post quá lớn
            // (500.000 ký tự là đủ cho chương dài nhất thực tế).
            'content' => ['required', 'string', 'max:500000'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
