<div class="row">
    <div class="col-md-8">
        <div class="mb-3">
            <label class="form-label">Tên truyện</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $story->title ?? '') }}">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Tác giả</label>
            <input type="text" name="author_name" class="form-control"
                   value="{{ old('author_name', $story->author_name ?? '') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control" rows="5">{{ old('description', $story->description ?? '') }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Thể loại</label>
            <div class="row">
                @foreach ($categories as $category)
                    <div class="col-md-4 form-check">
                        <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                               class="form-check-input"
                               {{ in_array($category->id, old('categories', $selectedCategoryIds ?? [])) ? 'checked' : '' }}>
                        <label class="form-check-label">{{ $category->name }}</label>
                    </div>
                @endforeach
            </div>
            @error('categories') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Ảnh bìa</label>
            <input type="file" name="cover_image" class="form-control">
            @isset($story)
                @if ($story->cover_image)
                    <img src="{{ Storage::url($story->cover_image) }}" class="mt-2 img-fluid">
                @endif
            @endisset
        </div>

        <div class="mb-3">
            <label class="form-label">Trạng thái</label>
            <select name="status" class="form-select">
                @foreach (['ongoing' => 'Đang tiến hành', 'completed' => 'Hoàn thành', 'paused' => 'Tạm dừng'] as $value => $label)
                    <option value="{{ $value }}" {{ old('status', $story->status ?? '') == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        @isset($story)
            <div class="mb-3 form-check form-switch">
                <input type="hidden" name="status_publish" value="0">
                <input type="checkbox" name="status_publish" value="1" class="form-check-input"
                       {{ old('status_publish', $story->status_publish) ? 'checked' : '' }}>
                <label class="form-check-label">Hiển thị công khai</label>
            </div>
        @endisset

        <hr>
        <h6>SEO</h6>
        <div class="mb-3">
            <label class="form-label">Meta title</label>
            <input type="text" name="meta_title" class="form-control"
                   value="{{ old('meta_title', $story->meta_title ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Meta description</label>
            <textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description', $story->meta_description ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Meta keywords</label>
            <input type="text" name="meta_keywords" class="form-control"
                   value="{{ old('meta_keywords', $story->meta_keywords ?? '') }}">
        </div>
    </div>
</div>