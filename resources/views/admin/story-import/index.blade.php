@extends('layouts.admin')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Nhập truyện</h1>
    <p class="text-muted mb-0">Nhập từ website được phép sử dụng nội dung, hoặc từ tệp .txt / .zip. Bạn có thể sửa thông tin truyện, chọn thể loại và tag trước khi nhập.</p>
</div>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.story-import.preview') }}">
                    @csrf
                    <label for="source-url" class="form-label fw-semibold">Từ URL trang truyện</label>
                    <div class="input-group">
                        <input id="source-url" type="url" name="url" class="form-control" value="{{ old('url') }}" placeholder="https://website.example/truyen/ten-truyen" required>
                        <button class="btn btn-primary" type="submit">Phân tích URL</button>
                    </div>
                    <div class="form-text">Hỗ trợ trang HTML có metadata phổ biến và liên kết chương hiển thị trong HTML. Website dùng JavaScript để tải chương có thể cần profile riêng trong <code>config/story_import.php</code>.</div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.story-import.file') }}" enctype="multipart/form-data">
                    @csrf
                    <label for="source-file" class="form-label fw-semibold">Hoặc từ tệp</label>
                    <div class="input-group">
                        <input id="source-file" type="file" name="file" class="form-control" accept=".txt,.zip,text/plain,application/zip" required>
                        <button class="btn btn-outline-primary" type="submit">Đọc tệp</button>
                    </div>
                    <div class="form-text">
                        <strong>.txt</strong>: một tệp, tự tách chương theo dòng tiêu đề "Chương N". <strong>.zip</strong>: mỗi chương một tệp .txt (xếp theo tên tệp, ví dụ <code>001.txt</code>). Mã hóa UTF-8, tối đa 20 MB và {{ \App\Services\StoryFileImportService::MAX_CHAPTERS }} chương.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if (!empty($preview))
    @php
        $isFile = ($preview['source_type'] ?? 'url') === 'file';
    @endphp
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $preview['title'] }}</h2>
                    <div class="text-muted small">{{ $preview['host'] }} · {{ count($preview['chapters']) }} chương tìm thấy</div>
                </div>
                <span class="badge text-bg-success">{{ $isFile ? 'Đã đọc tệp' : 'Đã phân tích' }}</span>
            </div>
            @if (!$isFile)
                <p class="small text-break"><strong>Nguồn:</strong> {{ $preview['source_url'] }}</p>
            @endif

            <form method="POST" action="{{ $isFile ? route('admin.story-import.file-store') : route('admin.story-import.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="token" value="{{ $previewToken }}">

                <h3 class="h6">Thông tin truyện</h3>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label" for="imp-title">Tên truyện *</label>
                        <input id="imp-title" name="title" required maxlength="255" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $preview['title']) }}">
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
                        <label class="form-label" for="imp-author">Tác giả</label>
                        <input id="imp-author" name="author_name" maxlength="255" class="form-control @error('author_name') is-invalid @enderror"
                               value="{{ old('author_name', $preview['author']) }}">
                        @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="imp-tags">Tags</label>
                        <input id="imp-tags" name="tags" maxlength="600" autocomplete="off" class="form-control @error('tags') is-invalid @enderror"
                               placeholder="hệ thống, trọng sinh, báo thù" value="{{ old('tags') }}">
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
                        <textarea id="imp-description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description', $preview['description']) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="imp-cover-url">Ảnh bìa (URL)</label>
                        <input id="imp-cover-url" type="url" name="cover_url" maxlength="2048" class="form-control @error('cover_url') is-invalid @enderror"
                               placeholder="https://..." value="{{ old('cover_url', $preview['cover_url'] ?? '') }}">
                        @error('cover_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Chỉ JPEG, PNG, WebP (tối đa 3 MB), tải về qua kết nối an toàn khi nhập.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="imp-cover-file">Hoặc tải ảnh bìa lên</label>
                        <input id="imp-cover-file" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control @error('cover_image') is-invalid @enderror">
                        @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Ảnh tải lên được ưu tiên hơn URL.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Thể loại</label>
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
                    <h3 class="h6 mb-0">Chọn chương cần nhập</h3>
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
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="permission_confirmed" value="1" id="permission-confirmed" required>
                    <label class="form-check-label" for="permission-confirmed">Tôi xác nhận có quyền nhập và lưu trữ nội dung này.</label>
                </div>
                <button class="btn btn-success" type="submit">Nhập các chương đã chọn thành bản nháp</button>
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
