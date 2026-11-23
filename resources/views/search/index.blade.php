@extends('layouts.public')

@section('title', 'Tìm kiếm và khám phá truyện')

@section('meta_description', 'Tìm truyện theo tên, tác giả, thể loại, trạng thái và thời gian cập nhật.')

@section('content')
<div class="section-heading">
    <span class="eyebrow">Khám phá kho truyện</span>
    <h2>Tìm kiếm truyện</h2>
</div>

<form action="{{ route('search') }}" method="GET" id="search-filters" class="search-filter-panel p-3 mb-4">
    @php
        // Active filters set by clicking a tag, an author or a poster name. Each chip links to the same
        // search without that one filter.
        $filterParams = array_filter([
            'q' => $keyword, 'category' => $categorySlug, 'tag' => $tagSlug, 'author' => $authorName,
            'poster' => $posterId ?: null, 'status' => $status, 'sort' => $sort, 'period' => $period,
        ], fn ($value) => $value !== '' && $value !== null);
        $chips = array_filter([
            'tag' => $tagSlug !== '' ? 'Tag: #'.($activeTag?->name ?? $tagSlug) : null,
            'author' => $authorName !== '' ? 'Tác giả: '.$authorName : null,
            'poster' => $posterId > 0 ? 'Người đăng: '.($posterName ?? '#'.$posterId) : null,
        ]);
    @endphp
    @if ($tagSlug !== '') <input type="hidden" name="tag" value="{{ $tagSlug }}"> @endif
    @if ($authorName !== '') <input type="hidden" name="author" value="{{ $authorName }}"> @endif
    @if ($posterId > 0) <input type="hidden" name="poster" value="{{ $posterId }}"> @endif
    @if ($chips !== [])
        <div class="mb-2 d-flex flex-wrap align-items-center gap-2">
            @foreach ($chips as $key => $label)
                <span class="badge bg-primary fs-6">{{ $label }}</span>
                <a href="{{ route('search', \Illuminate\Support\Arr::except($filterParams, [$key])) }}" class="small me-2">Bỏ lọc {{ ['tag' => 'tag', 'author' => 'tác giả', 'poster' => 'người đăng'][$key] }}</a>
            @endforeach
        </div>
    @endif
    <div class="row g-2">
        <div class="col-lg-6">
            <label for="search-keyword" class="form-label fw-semibold">Tên truyện, tác giả, mô tả hoặc từ khóa</label>
            <input id="search-keyword" type="search" name="q" class="form-control" maxlength="100" placeholder="Nhập từ khóa..." value="{{ $keyword }}">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="search-category" class="form-label fw-semibold">Thể loại</label>
            <select id="search-category" name="category" class="form-select">
                <option value="">Tất cả thể loại</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->slug }}" {{ $categorySlug === $cat->slug ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="search-status" class="form-label fw-semibold">Trạng thái</label>
            <select id="search-status" name="status" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <option value="ongoing" {{ $status === 'ongoing' ? 'selected' : '' }}>Đang tiến hành</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Hoàn thành</option>
                <option value="paused" {{ $status === 'paused' ? 'selected' : '' }}>Tạm dừng</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-4">
            <label for="search-sort" class="form-label fw-semibold">Sắp xếp</label>
            <select id="search-sort" name="sort" class="form-select">
                <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Truyện mới đăng</option>
                <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Phù hợp nhất</option>
                <option value="updated" {{ $sort === 'updated' ? 'selected' : '' }}>Mới cập nhật chương</option>
                <option value="most_viewed" {{ $sort === 'most_viewed' ? 'selected' : '' }}>Nhiều lượt xem</option>
                <option value="top_rated" {{ $sort === 'top_rated' ? 'selected' : '' }}>Đánh giá cao</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-4">
            <label for="search-period" class="form-label fw-semibold">Thời gian cập nhật</label>
            <select id="search-period" name="period" class="form-select">
                <option value="">Mọi thời gian</option>
                <option value="week" {{ $period === 'week' ? 'selected' : '' }}>7 ngày gần đây</option>
                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>30 ngày gần đây</option>
            </select>
        </div>
        <div class="col-lg-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-jade flex-grow-1">Tìm kiếm</button>
            <a href="{{ route('search') }}" class="btn btn-outline-jade">Đặt lại</a>
        </div>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3" aria-live="polite" aria-atomic="true">
    <p class="text-muted small mb-0">Tìm thấy {{ number_format($stories->total()) }} truyện phù hợp.</p>
</div>

<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($stories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @empty
        <div class="col-12">
            <p class="text-muted border rounded-3 p-4 mb-0">Không tìm thấy truyện phù hợp. Hãy thử từ khóa hoặc bộ lọc khác.</p>
        </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $stories->links() }}
</div>
@endsection
