@php
    $type = old('type', $ad->type ?: 'image');
@endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="ad-name">Tên quảng cáo * <span class="text-muted small">(chỉ để quản lý, khách không thấy)</span></label>
        <input id="ad-name" name="name" required maxlength="120" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $ad->name) }}">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ad-sort">Thứ tự ưu tiên</label>
        <input id="ad-sort" type="number" min="0" max="999" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $ad->sort_order ?? 0) }}">
        <div class="form-text">Số nhỏ hiện trước.</div>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label class="form-label" for="ad-placement">Vị trí *</label>
        <select id="ad-placement" name="placement" required class="form-select @error('placement') is-invalid @enderror">
            <option value="">— Chọn vị trí —</option>
            @foreach ($placements as $key => $info)
                <option value="{{ $key }}" @selected(old('placement', $ad->placement) === $key)>{{ $info['label'] }}</option>
            @endforeach
        </select>
        @error('placement')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ad-type">Loại *</label>
        <select id="ad-type" name="type" class="form-select">
            <option value="image" @selected($type === 'image')>Ảnh banner + đường dẫn</option>
            <option value="code" @selected($type === 'code')>Mã quảng cáo (từ mạng quảng cáo)</option>
        </select>
    </div>

    <div class="col-12 ad-type-image">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="ad-image">Ảnh banner {{ $ad->image_path ? '' : '*' }}</label>
                <input id="ad-image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control @error('image') is-invalid @enderror">
                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG, WebP hoặc GIF, tối đa 1 MB, kích thước bất kỳ. Khi hiển thị ảnh luôn rộng 160 px. Rìa trái kéo dài hết chiều cao màn hình, rìa phải cao tối đa 600 px; ảnh không khớp thì bị cắt bớt.</div>
                @if ($ad->image_path)
                    <img src="{{ $ad->imageUrl() }}" alt="Ảnh hiện tại" class="mt-2 border rounded" style="max-width: 120px">
                    <div class="form-text">Để trống nếu giữ ảnh hiện tại.</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ad-link">Đường dẫn khi bấm vào *</label>
                <input id="ad-link" type="url" name="link_url" maxlength="2048" placeholder="https://..." class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url', $ad->link_url) }}">
                @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <label class="form-label mt-3" for="ad-alt">Mô tả ảnh (alt)</label>
                <input id="ad-alt" name="alt_text" maxlength="150" class="form-control" value="{{ old('alt_text', $ad->alt_text) }}">
            </div>
        </div>
    </div>

    <div class="col-12 ad-type-code">
        <label class="form-label" for="ad-code">Mã quảng cáo *</label>
        <textarea id="ad-code" name="html_code" rows="6" maxlength="5000" class="form-control font-monospace @error('html_code') is-invalid @enderror" placeholder="&lt;script ...&gt;&lt;/script&gt;">{{ old('html_code', $ad->html_code) }}</textarea>
        @error('html_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="alert alert-warning small mt-2 mb-0">
            Mã này được chèn nguyên văn vào mọi trang thuộc vị trí đã chọn, kể cả đoạn script. Chỉ dán mã từ mạng quảng cáo bạn tin cậy.
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="ad-start">Bắt đầu</label>
        <input id="ad-start" type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $ad->starts_at?->format('Y-m-d\TH:i')) }}">
        <div class="form-text">Để trống: chạy ngay.</div>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ad-end">Kết thúc</label>
        <input id="ad-end" type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $ad->ends_at?->format('Y-m-d\TH:i')) }}">
        <div class="form-text">Để trống: không giới hạn.</div>
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="ad-active" name="is_active" value="1" @checked(old('is_active', $ad->is_active))>
            <label class="form-check-label fw-semibold" for="ad-active">Đang bật</label>
        </div>
    </div>
</div>

<script>
(() => {
    const select = document.getElementById('ad-type');
    const sync = () => {
        const isImage = select.value === 'image';
        document.querySelectorAll('.ad-type-image').forEach((el) => el.classList.toggle('d-none', !isImage));
        document.querySelectorAll('.ad-type-code').forEach((el) => el.classList.toggle('d-none', isImage));
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>
