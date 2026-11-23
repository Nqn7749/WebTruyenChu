<div class="row">
<div class="col-lg-8">
    <div class="mb-3"><label class="form-label">Tên truyện *</label><input name="title" required maxlength="255" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $story->title ?? '') }}">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-3"><label class="form-label">Bút danh</label><input name="author_name" maxlength="255" class="form-control" value="{{ old('author_name', $story->author_name ?? auth()->user()->name) }}"></div>
    <div class="mb-3"><label class="form-label">Giới thiệu</label><textarea name="description" rows="6" class="form-control">{{ old('description', $story->description ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">Thể loại *</label><div class="row">
    @foreach ($categories as $category)
        <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}" {{ in_array($category->id, old('categories', $selectedCategoryIds ?? [])) ? 'checked' : '' }}><label class="form-check-label">{{ $category->name }}</label></div>
    @endforeach
    </div>@error('categories')<div class="text-danger small">{{ $message }}</div>@enderror</div>
    <div class="mb-3">
        <label class="form-label" for="story-tags">Tags</label>
        <input id="story-tags" name="tags" maxlength="600" autocomplete="off"
               class="form-control @error('tags') is-invalid @enderror"
               placeholder="Ví dụ: hệ thống, trọng sinh, báo thù"
               value="{{ old('tags', $tagNames ?? '') }}">
        @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Tự thêm tag, ngăn cách bằng dấu phẩy (tối đa 10 tag, mỗi tag tối đa 30 ký tự). Người đọc bấm vào tag để tìm các truyện cùng tag.</div>
        @if (($popularTags ?? collect())->isNotEmpty())
            <div class="mt-2 d-flex flex-wrap gap-1" aria-label="Tag phổ biến">
                <span class="small text-muted me-1">Phổ biến:</span>
                @foreach ($popularTags as $popular)
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 tag-suggestion" data-tag="{{ $popular->name }}">{{ $popular->name }}</button>
                @endforeach
            </div>
            <script>
                document.querySelectorAll('.tag-suggestion').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const input = document.getElementById('story-tags');
                        const current = input.value.split(/[,;
]+/).map(s => s.trim()).filter(Boolean);
                        if (!current.some(t => t.toLowerCase() === button.dataset.tag.toLowerCase())) {
                            current.push(button.dataset.tag);
                            input.value = current.join(', ');
                        }
                        input.focus();
                    });
                });
            </script>
        @endif
    </div>
    <div class="mb-3"><label class="form-label">Tình trạng truyện</label><select name="status" class="form-select">
    @foreach (['ongoing' => 'Đang tiến hành', 'completed' => 'Hoàn thành', 'paused' => 'Tạm dừng'] as $value => $label)
        <option value="{{ $value }}" @selected(old('status', $story->status ?? 'ongoing') === $value)>{{ $label }}</option>
    @endforeach
    </select></div>
</div>
<div class="col-lg-4"><label class="form-label">Ảnh bìa</label><input type="file" name="cover_image" accept="image/*" class="form-control">
@if (!empty($story?->cover_image))<img src="{{ Storage::url($story->cover_image) }}" class="img-fluid rounded mt-3" alt="Ảnh bìa hiện tại">@endif
@error('cover_image')<div class="text-danger small">{{ $message }}</div>@enderror</div>
</div>
