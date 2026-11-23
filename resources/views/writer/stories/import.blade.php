@extends('layouts.public')
@section('title', 'Đăng truyện từ tệp')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Đăng truyện từ tệp</h1>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-outline-secondary btn-sm">Quay lại</a>
</div>

@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('writer.stories.import.upload') }}" enctype="multipart/form-data">
            @csrf
            <label for="story-file" class="form-label fw-semibold">Chọn tệp truyện của bạn</label>
            <div class="input-group">
                <input id="story-file" type="file" name="file" class="form-control" accept=".txt,.zip,text/plain,application/zip" required>
                <button class="btn btn-primary" type="submit">Đọc tệp</button>
            </div>
            <div class="form-text">
                <strong>.txt</strong>: một tệp, tự tách chương theo dòng tiêu đề như "Chương 1: Mở đầu".
                <strong>.zip</strong>: mỗi chương một tệp .txt, xếp theo tên (ví dụ <code>001.txt</code>, <code>002.txt</code>).
                Mã hóa UTF-8, tối đa 10 MB và {{ $maxChapters }} chương. Bạn có thể xem lại và sửa trước khi đăng.
            </div>
        </form>
    </div>
</div>

@if (!empty($preview))
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div class="text-muted">Tìm thấy <strong>{{ count($preview['chapters']) }}</strong> chương trong tệp.</div>
            <span class="badge text-bg-success">Đã đọc tệp</span>
        </div>

        <form method="POST" action="{{ route('writer.stories.import.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="token" value="{{ $previewToken }}">

            <h2 class="h6">Thông tin truyện</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label" for="imp-title">Tên truyện *</label>
                    <input id="imp-title" name="title" required maxlength="255" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $preview['title']) }}">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imp-status">Tình trạng</label>
                    <select id="imp-status" name="status" class="form-select">
                        @foreach (['ongoing' => 'Đang tiến hành', 'completed' => 'Hoàn thành', 'paused' => 'Tạm dừng'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'ongoing') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imp-author">Bút danh</label>
                    <input id="imp-author" name="author_name" maxlength="255" class="form-control" value="{{ old('author_name', auth()->user()->name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imp-tags">Tags</label>
                    <input id="imp-tags" name="tags" maxlength="600" autocomplete="off" class="form-control @error('tags') is-invalid @enderror" placeholder="hệ thống, trọng sinh, báo thù" value="{{ old('tags') }}">
                    @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Ngăn cách bằng dấu phẩy, tối đa 10 tag.</div>
                    @if ($popularTags->isNotEmpty())
                        <div class="mt-1 d-flex flex-wrap gap-1">
                            @foreach ($popularTags as $popular)
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 tag-suggestion" data-tag="{{ $popular->name }}">{{ $popular->name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label" for="imp-description">Giới thiệu</label>
                    <textarea id="imp-description" name="description" rows="5" class="form-control">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imp-cover">Ảnh bìa</label>
                    <input id="imp-cover" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control @error('cover_image') is-invalid @enderror">
                    @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">JPEG, PNG hoặc WebP, tối đa 3 MB.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Thể loại * <span class="text-muted small">(chọn ít nhất một)</span></label>
                    @php($selectedCategories = collect(old('categories', []))->map(fn ($id) => (int) $id)->all())
                    <div class="row">
                        @foreach ($categories as $category)
                            <div class="col-6 col-md-3 form-check">
                                <input class="form-check-input" type="checkbox" name="categories[]" id="imp-cat-{{ $category->id }}" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true))>
                                <label class="form-check-label {{ $category->parent_id ? 'small text-muted' : '' }}" for="imp-cat-{{ $category->id }}">{{ $category->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    @error('categories')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h2 class="h6 mb-0">Chọn chương cần đăng</h2>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="select-all">Chọn tất cả</button>
                    <button type="button" class="btn btn-outline-secondary" id="select-none">Bỏ chọn</button>
                </div>
            </div>
            <div class="border rounded p-3 mb-3" style="max-height: 360px; overflow:auto">
                @foreach ($preview['chapters'] as $index => $chapter)
                    <label class="d-flex align-items-start gap-2 py-1">
                        <input class="form-check-input mt-1 chapter-choice" type="checkbox" name="chapters[]" value="{{ $index }}" checked>
                        <span>{{ $chapter['title'] }}</span>
                    </label>
                @endforeach
            </div>

            <div class="form-check form-switch mb-2">
                <input type="hidden" name="publish_chapters" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="publish-chapters" name="publish_chapters" value="1" @checked(old('publish_chapters'))>
                <label class="form-check-label fw-semibold" for="publish-chapters">Đăng công khai các chương ngay</label>
                <div class="form-text">Tắt để lưu các chương ở bản nháp, rồi đăng từng chương khi sẵn sàng. Truyện luôn được công khai ngay sau khi đăng.</div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="authorship_confirmed" value="1" id="authorship-confirmed" required>
                <label class="form-check-label" for="authorship-confirmed">Tôi là tác giả hoặc có quyền đăng tải nội dung này.</label>
            </div>
            <button class="btn btn-primary" type="submit">Đăng truyện</button>
        </form>
    </div>
</div>
@endif

<script>
document.getElementById('select-all')?.addEventListener('click', () => {
    document.querySelectorAll('.chapter-choice').forEach((input) => input.checked = true);
});
document.getElementById('select-none')?.addEventListener('click', () => {
    document.querySelectorAll('.chapter-choice').forEach((input) => input.checked = false);
});
document.querySelectorAll('.tag-suggestion').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById('imp-tags');
        const current = input.value.split(/[,;\n]+/).map((s) => s.trim()).filter(Boolean);
        if (!current.some((t) => t.toLowerCase() === button.dataset.tag.toLowerCase())) {
            current.push(button.dataset.tag);
            input.value = current.join(', ');
        }
        input.focus();
    });
});
</script>
@endsection
