@extends('layouts.public')
@section('title', 'Thêm chương từ tệp')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Thêm chương từ tệp</h1>
        <p class="text-muted mb-0">{{ $story->title }} · hiện có {{ $chapterCount }} chương{{ $lastNumber ? ', chương cuối là số '.$lastNumber : '' }}</p>
    </div>
    <a href="{{ route('writer.stories.chapters.index', $story) }}" class="btn btn-outline-secondary btn-sm">Quay lại danh sách chương</a>
</div>

@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('writer.stories.chapters.import.upload', $story) }}" enctype="multipart/form-data">
            @csrf
            <label for="chapter-file" class="form-label fw-semibold">Chọn tệp chứa các chương</label>
            <div class="input-group">
                <input id="chapter-file" type="file" name="file" class="form-control" accept=".txt,.zip,text/plain,application/zip" required>
                <button class="btn btn-primary" type="submit">Đọc tệp</button>
            </div>
            <div class="form-text">
                <strong>.txt</strong>: một tệp, tự tách chương theo dòng tiêu đề như "Chương 1: Mở đầu".
                <strong>.zip</strong>: mỗi chương một tệp .txt, xếp theo tên (ví dụ <code>001.txt</code>, <code>002.txt</code>).
                Mã hóa UTF-8, tối đa 10 MB và {{ $maxChapters }} chương mỗi lần. Bạn xem lại danh sách chương trước khi thêm.
            </div>
        </form>
    </div>
</div>

@if (!empty($preview))
@php
    $defaultNumbering = old('numbering', $conflicts === [] ? 'file' : 'append');
@endphp
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div class="text-muted">Tìm thấy <strong>{{ count($preview['chapters']) }}</strong> chương trong tệp.</div>
            <span class="badge text-bg-success">Đã đọc tệp</span>
        </div>

        <form method="POST" action="{{ route('writer.stories.chapters.import.store', $story) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $previewToken }}">

            <h2 class="h6">Cách đánh số chương</h2>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="numbering" id="num-file" value="file" @checked($defaultNumbering === 'file')>
                    <label class="form-check-label" for="num-file">Theo số ghi trong tệp (Chương 1, Chương 2...)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="numbering" id="num-append" value="append" @checked($defaultNumbering === 'append')>
                    <label class="form-check-label" for="num-append">
                        Nối tiếp sau chương cuối hiện có (sẽ bắt đầu từ chương {{ $lastNumber + 1 }})
                    </label>
                </div>
                @if ($conflicts !== [])
                    <div class="alert alert-warning mt-2 mb-0 py-2" id="conflict-note">
                        {{ count($conflicts) }} chương trong tệp trùng số với chương đã có trong truyện (đánh dấu bên dưới).
                        Chọn "Nối tiếp" hoặc bỏ chọn các chương đó.
                    </div>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h2 class="h6 mb-0">Chọn chương cần thêm</h2>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="select-all">Chọn tất cả</button>
                    <button type="button" class="btn btn-outline-secondary" id="select-none">Bỏ chọn</button>
                </div>
            </div>
            <div class="border rounded p-3 mb-3" style="max-height: 360px; overflow:auto">
                @foreach ($preview['chapters'] as $index => $chapter)
                    <label class="d-flex align-items-start gap-2 py-1">
                        <input class="form-check-input mt-1 chapter-choice" type="checkbox" name="chapters[]" value="{{ $index }}" @checked(!in_array($index, $conflicts, true) || $defaultNumbering === 'append')>
                        <span>
                            {{ $chapter['title'] }}
                            @if (in_array($index, $conflicts, true))
                                <span class="badge text-bg-warning conflict-badge">đã có số {{ $chapter['number'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="form-check form-switch mb-3">
                <input type="hidden" name="publish_chapters" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="publish-chapters" name="publish_chapters" value="1" @checked(old('publish_chapters'))>
                <label class="form-check-label fw-semibold" for="publish-chapters">Đăng công khai các chương ngay</label>
                <div class="form-text">Tắt để lưu ở bản nháp rồi đăng từng chương khi sẵn sàng. Bật thì người theo dõi truyện nhận thông báo chương mới.</div>
            </div>
            <button class="btn btn-primary" type="submit">Thêm các chương đã chọn</button>
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
// The "already exists" markers only matter when numbering follows the file.
const syncConflicts = () => {
    const follow = document.getElementById('num-file')?.checked;
    document.querySelectorAll('.conflict-badge').forEach((el) => el.style.display = follow ? '' : 'none');
    const note = document.getElementById('conflict-note');
    if (note) note.style.display = follow ? '' : 'none';
};
document.querySelectorAll('input[name="numbering"]').forEach((el) => el.addEventListener('change', syncConflicts));
syncConflicts();
</script>
@endsection
