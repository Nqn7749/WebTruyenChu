@php
    $values = old('selectors', $source?->selectors ?? [
        'story_link' => '//a[contains(@class,"story-link")]',
        'story_title' => '//h1',
        'story_author' => '',
        'story_description' => '//div[contains(@class,"description")]',
        'chapter_link' => '//a[contains(@class,"chapter-link")]',
        'chapter_title' => '//h1',
        'chapter_content' => '//div[contains(@class,"chapter-content")]',
    ]);
@endphp
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Tên nguồn</label><input name="name" class="form-control" required maxlength="120" value="{{ old('name', $source?->name) }}"></div>
    <div class="col-md-4"><label class="form-label">Base URL (HTTPS)</label><input name="base_url" type="url" class="form-control" required value="{{ old('base_url', $source?->base_url) }}" placeholder="https://example.org"></div>
    <div class="col-md-4"><label class="form-label">URL danh sách truyện</label><input name="listing_url" type="url" class="form-control" required value="{{ old('listing_url', $source?->listing_url) }}"></div>
    @foreach ([
        'story_link' => 'XPath liên kết truyện',
        'story_title' => 'XPath tên truyện',
        'story_author' => 'XPath tác giả (không bắt buộc)',
        'story_description' => 'XPath mô tả (không bắt buộc)',
        'chapter_link' => 'XPath liên kết chương',
        'chapter_title' => 'XPath tên chương',
        'chapter_content' => 'XPath nội dung chương',
    ] as $key => $label)
        <div class="col-md-6"><label class="form-label">{{ $label }}</label><input name="selectors[{{ $key }}]" class="form-control" maxlength="500" value="{{ $values[$key] ?? '' }}" {{ in_array($key, ['story_author', 'story_description']) ? '' : 'required' }}></div>
    @endforeach
</div>
<div class="form-check mt-3">
    <input type="checkbox" class="form-check-input" name="permission_confirmed" value="1" id="permission_confirmed" required {{ old('permission_confirmed', $source?->permission_confirmed) ? 'checked' : '' }}>
    <label class="form-check-label" for="permission_confirmed">Tôi xác nhận có quyền thu thập và lưu trữ nội dung từ nguồn này.</label>
</div>
<div class="form-check mt-2">
    <input type="checkbox" class="form-check-input" name="enabled" value="1" id="crawler_enabled" {{ old('enabled', $source?->enabled) ? 'checked' : '' }}>
    <label class="form-check-label" for="crawler_enabled">Cho phép crawler chạy</label>
</div>
<p class="form-text mt-2">Các XPath phải trỏ đúng nội dung do nguồn cho phép. Giới hạn mỗi lượt: 20 truyện, 50 chương/truyện; nội dung nhập vào luôn ở trạng thái nháp và chưa công khai.</p>
