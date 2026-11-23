<?php

namespace App\Http\Requests\Admin;

use App\Support\TagInput;
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
        // Chỉ tài khoản đã đăng nhập mới được đăng truyện.
        return $this->user() !== null;
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
            // Chặn <> trong tiêu đề để không thể chèn thẻ HTML qua tiêu đề
            // (phòng thủ thêm cho lỗi XSS ở meta og:title).
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            // Chỉ nhận ảnh raster (jpeg/png/webp): rule 'image' vẫn cho file
            // SVG qua, mà SVG có thể chứa script và file lưu ở disk public
            // (stored XSS khi mở trực tiếp).
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            // description là longText, meta_description là TEXT (tối đa
            // 65.535 byte): emoji tốn 4 byte/ký tự nên giới hạn 16.000
            // ký tự (~64.000 byte) để nhập quá dài trả lỗi validation
            // thay vì lỗi 500 của DB. Cùng lỗi M12 đã sửa cho mô tả thể loại.
            'description' => ['nullable', 'string', 'max:1000000'],
            'status' => ['required', Rule::in(['ongoing', 'completed', 'paused'])],
            'categories' => ['required', 'array', 'max:10'],
            'categories.*' => ['exists:categories,id'],
            // Free text: tags separated by commas. Authors create tags themselves.
            'tags' => ['nullable', 'string', 'max:600', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($message = TagInput::validate((string) $value)) {
                    $fail($message);
                }
            }],
            'meta_title' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'meta_description' => ['nullable', 'string', 'max:16000'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ];
    }
}
