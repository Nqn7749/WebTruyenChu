<div class="mb-3">
    <label class="form-label">Số chương</label>
    <input type="number" name="chapter_number" class="form-control @error('chapter_number') is-invalid @enderror"
           value="{{ old('chapter_number', $chapterNumber) }}">
    @error('chapter_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Tiêu đề chương</label>
    <input type="text" name="title" class="form-control"
           value="{{ old('title', $chapter->title ?? '') }}">
</div>

<div class="mb-3">
    <label class="form-label">Nội dung</label>
    <textarea name="content" class="form-control @error('content') is-invalid @enderror" rows="15">{{ old('content', $chapter->content ?? '') }}</textarea>
    @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3 form-check form-switch">
    <input type="hidden" name="status" value="0">
    <input type="checkbox" name="status" value="1" class="form-check-input"
           {{ old('status', $chapter->status ?? true) ? 'checked' : '' }}>
    <label class="form-check-label">Đăng công khai</label>
</div>