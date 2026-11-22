@extends('layouts.admin')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Nhập truyện từ URL</h1>
    <p class="text-muted mb-0">Phân tích thông tin truyện và danh sách chương từ website được phép sử dụng nội dung.</p>
</div>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.story-import.preview') }}">
            @csrf
            <label for="source-url" class="form-label fw-semibold">URL trang truyện</label>
            <div class="input-group">
                <input id="source-url" type="url" name="url" class="form-control" value="{{ old('url') }}" placeholder="https://website.example/truyen/ten-truyen" required>
                <button class="btn btn-primary" type="submit">Phân tích URL</button>
            </div>
            <div class="form-text">Bản đầu hỗ trợ trang HTML có metadata phổ biến và liên kết chương hiển thị trong HTML. Website dùng JavaScript để tải chương có thể cần adapter riêng.</div>
        </form>
    </div>
</div>

@if (!empty($preview))
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $preview['title'] }}</h2>
                    <div class="text-muted small">{{ $preview['host'] }} · {{ count($preview['chapters']) }} chương tìm thấy</div>
                </div>
                <span class="badge text-bg-success">Đã phân tích</span>
            </div>

            @if ($preview['author'])
                <p class="mb-2"><strong>Tác giả:</strong> {{ $preview['author'] }}</p>
            @endif
            @if ($preview['description'])
                <p class="text-muted">{{ $preview['description'] }}</p>
            @endif
            <p class="small text-break"><strong>Nguồn:</strong> {{ $preview['source_url'] }}</p>

            <form method="POST" action="{{ route('admin.story-import.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $previewToken }}">
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
                    <label class="form-check-label" for="permission-confirmed">Tôi xác nhận có quyền nhập và lưu trữ nội dung từ nguồn này.</label>
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
</script>
@endsection
