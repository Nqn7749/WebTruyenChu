<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                Rule::notIn([$category->id]),
                function ($attribute, $value, $fail) use ($category) {
                    if ($value && $this->isDescendantOf((int) $value, $category->id)) {
                        $fail('Không thể chọn danh mục con/cháu làm danh mục cha.');
                    }
                },
            ],
            // Chặn <> trong tên để không thể chèn thẻ HTML qua tên
            // (tên thể loại được đưa vào thẻ <title> ở trang thể loại).
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            // Cột description là TEXT (tối đa 65.535 byte): 20.000 ký tự là
            // mức an toàn cho tiếng Việt (3 byte/ký tự), quá dài trả lỗi
            // validation thay vì lỗi 500 của DB.
            'description' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Kiểm tra xem $candidateParentId có phải là con/cháu (descendant)
     * của $categoryId hay không, bằng cách đi ngược lên từ candidate
     * qua parent_id cho tới khi gặp $categoryId hoặc hết gốc.
     */
    private function isDescendantOf(int $candidateParentId, int $categoryId): bool
    {
        $current = Category::find($candidateParentId);

        while ($current) {
            if ($current->id === $categoryId) {
                return true;
            }

            $current = $current->parent_id
                ? Category::find($current->parent_id)
                : null;
        }

        return false;
    }
}
