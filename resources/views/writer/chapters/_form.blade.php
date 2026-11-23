<div class="mb-3">
    <label class="form-label" for="chapter-number">Số chương *</label>
    <input id="chapter-number" type="number" min="1" required name="chapter_number"
           class="form-control @error('chapter_number') is-invalid @enderror"
           value="{{ old('chapter_number', $chapter->chapter_number ?? $chapterNumber) }}">
    @error('chapter_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="chapter-title">Tiêu đề chương</label>
    <input id="chapter-title" name="title" maxlength="255" class="form-control"
           value="{{ old('title', $chapter->title ?? '') }}">
</div>
<div class="mb-3">
    <label class="form-label" for="chapter-content">Nội dung *</label>
    @include('components.chapter-editor', ['value' => old('content', $chapter->content ?? ''), 'invalid' => $errors->has('content')])
    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="form-check form-switch mb-4">
    <input class="form-check-input" type="checkbox" role="switch" id="chapter-status"
           name="status" value="1" @checked((bool) old('status', $chapter->status ?? false))>
    <label class="form-check-label fw-semibold" for="chapter-status">Đăng chương công khai</label>
    <div class="form-text">Bật để độc giả đọc được chương ngay sau khi lưu. Tắt để giữ chương ở bản nháp.</div>
</div>
