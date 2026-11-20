<?php

namespace App\Http\Requests\Admin;

use App\Models\Story;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255'],
            // Chỉ nhận ảnh raster (jpeg/png/webp): rule 'image' vẫn cho file
            // SVG qua, mà SVG có thể chứa script và file lưu ở disk public
            // (stored XSS khi mở trực tiếp).
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000000'],
            'status' => ['required', Rule::in(['ongoing', 'completed', 'paused'])],
            'status_publish' => ['nullable', 'boolean'],
            'categories' => ['required', 'array', 'max:10'],
            'categories.*' => ['exists:categories,id'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['exists:tags,id'],
            'meta_title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'meta_description' => ['nullable', 'string', 'max:60000'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }
}
