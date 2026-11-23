<?php

namespace App\Http\Requests\Admin;

use App\Models\Story;
use App\Support\ChapterContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Request này dùng chung cho cả admin và writer (NL-11): admin
        // được thêm chương cho truyện bất kỳ, writer chỉ cho truyện mình.
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->role?->slug === 'admin') {
            return true;
        }

        $story = $this->route('story');

        return $story instanceof Story && $story->user_id === $user->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Số chương không được trùng trong cùng một truyện (DB đã bỏ
            // unique này ở migration 2026-11-16, nên chặn ở validation).
            'chapter_number' => ['required', 'integer', 'min:1', 'max:100000'],
            'title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            // Cột content là longText nhưng vẫn cần max để tránh post quá lớn
            // (500.000 ký tự là đủ cho chương dài nhất thực tế).
            'content' => ['required', 'string', 'max:500000', function (string $attribute, mixed $value, \Closure $fail): void {
                // The rich-text editor submits "<p><br></p>" for an empty body.
                if (ChapterContent::plainText((string) $value) === '') {
                    $fail('Nội dung chương không được để trống.');
                }
            }],
            'status' => ['nullable', 'boolean'],
        ];

        if ($this->storyId() !== null) {
            $rules['chapter_number'][] = Rule::unique('chapters', 'chapter_number')
                ->where('story_id', $this->storyId())
                ->whereNull('deleted_at');
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
