@extends('layouts.public')

@section('title', 'Danh sách truyện')

@section('meta_description', 'Duyệt và lọc truyện theo trạng thái, thể loại, số chương tại Web Đọc Truyện Chữ.')

@section('content')

<div class="section-heading">
    <span class="eyebrow">Khám phá</span>
    <h2>Danh sách truyện</h2>
</div>

<div class="row g-4">
    {{-- BỘ LỌC --}}
    <div class="col-lg-3">
        <form action="{{ route('stories.index') }}" method="GET" class="border rounded-3 p-3" style="background: var(--paper-raised); border-color: var(--sand-border) !important;">

            {{-- Sắp xếp --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Sắp xếp theo</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="latest_chapter" {{ $sort === 'latest_chapter' ? 'selected' : '' }}>
                        Mới thêm chương
                    </option>
                    <option value="newest_story" {{ $sort === 'newest_story' ? 'selected' : '' }}>
                        Mới đăng truyện
                    </option>
                    <option value="most_viewed" {{ $sort === 'most_viewed' ? 'selected' : '' }}>
                        Nhiều lượt xem nhất
                    </option>
                    <option value="top_rated" {{ $sort === 'top_rated' ? 'selected' : '' }}>
                        Đánh giá cao nhất
                    </option>
                </select>
            </div>

            {{-- Trạng thái --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Trạng thái</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Tất cả --</option>
                    @foreach (['ongoing' => 'Đang tiến hành', 'completed' => 'Hoàn thành', 'paused' => 'Tạm dừng'] as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Số chương --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Số chương</label>
                <select name="chapters" class="form-select form-select-sm">
                    <option value="">-- Tất cả --</option>
                    <option value="under_300" {{ $chapterRange === 'under_300' ? 'selected' : '' }}>Dưới 300 chương</option>
                    <option value="300_600" {{ $chapterRange === '300_600' ? 'selected' : '' }}>300 - 600 chương</option>
                    <option value="600_1000" {{ $chapterRange === '600_1000' ? 'selected' : '' }}>600 - 1000 chương</option>
                    <option value="over_1000" {{ $chapterRange === 'over_1000' ? 'selected' : '' }}>Trên 1000 chương</option>
                </select>
            </div>

            {{-- Thể loại --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Thể loại</label>
                <div style="max-height: 320px; overflow-y: auto; border-color: var(--sand-border) !important;" class="border rounded-2 p-2">
                    @foreach ($categories->whereNull('parent_id') as $parent)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]"
                                   value="{{ $parent->id }}" id="cat-{{ $parent->id }}"
                                   {{ in_array($parent->id, $categoryIds, true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="cat-{{ $parent->id }}">
                                {{ $parent->name }}
                            </label>
                        </div>

                        @foreach ($categories->where('parent_id', $parent->id) as $child)
                            <div class="form-check ms-3">
                                <input class="form-check-input" type="checkbox" name="categories[]"
                                       value="{{ $child->id }}" id="cat-{{ $child->id }}"
                                       {{ in_array($child->id, $categoryIds, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="cat-{{ $child->id }}">
                                    {{ $child->name }}
                                </label>
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <div class="form-text">Chọn nhiều thể loại: truyện thuộc bất kỳ thể loại nào đã chọn sẽ hiển thị.</div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-jade btn-sm">Lọc truyện</button>
                <a href="{{ route('stories.index') }}" class="btn btn-outline-jade btn-sm">Xóa bộ lọc</a>
            </div>
        </form>
    </div>

    {{-- KẾT QUẢ --}}
    <div class="col-lg-9">
        <p class="text-muted small mb-3">Tìm thấy {{ $stories->total() }} truyện.</p>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            @forelse ($stories as $story)
                <div class="col"><x-story-card :story="$story" /></div>
            @empty
                <p class="text-muted">Không có truyện nào phù hợp với bộ lọc.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $stories->links() }}
        </div>
    </div>
</div>

@endsection