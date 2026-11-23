<?php

namespace App\Http\Requests\Admin;

use App\Models\Story;
use App\Support\TagInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Chỉ chủ truyện hoặc admin mới được sửa truyện này.
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $story = $this->route('story');

        if (! $story) {
            return false;
        }

        $storyUserId = $story instanceof Story ? $story->user_id : null;

        return $storyUserId === $user->id
            || ($user->role?->slug === 'admin');
    }

    /**
     * Older clients posted tags[] as an array; the field is now one comma-separated text.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('tags'))) {
            $this->merge(['tags' => implode(', ', array_filter($this->input('tags'), 'is_string'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            // Chỉ nhận ảnh raster (jpeg/png/webp): rule 'image' vẫn cho file
            // SVG qua, mà SVG có thể chứa script và file lưu ở disk public
            // (stored XSS khi mở trực tiếp).
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'description' => ['nullable', 'string', 'max:1000000'],
            'status' => ['required', Rule::in(['ongoing', 'completed', 'paused'])],
            'status_publish' => ['nullable', 'boolean'],
            'categories' => ['required', 'array', 'max:10'],
            'categories.*' => ['exists:categories,id'],
            // Free text: tags separated by commas. Authors create tags themselves.
            'tags' => ['nullable', 'string', 'max:600', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($message = TagInput::validate((string) $value)) {
                    $fail($message);
                }
            }],
            'meta_title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            // TEXT chỉ chứa 65.535 byte, emoji tốn 4 byte/ký tự nên
            // max 16.000 ký tự (~64.000 byte), nhập quá dài trả 422 thay vì 500.
            'meta_description' => ['nullable', 'string', 'max:16000'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }
}
