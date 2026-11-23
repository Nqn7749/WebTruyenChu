@extends('layouts.public')

@section('title', 'Bảng xếp hạng truyện')
@section('meta_description', 'Bảng xếp hạng truyện tự động cập nhật: đọc nhiều, xem nhiều, yêu thích nhiều và đánh giá cao theo tuần, tháng và mọi thời gian.')

@section('content')
<div class="section-heading">
    <span class="eyebrow">Tự động cập nhật theo lượt đọc thật</span>
    <h2>Bảng xếp hạng</h2>
</div>

<ul class="nav nav-pills mb-3" aria-label="Loại bảng xếp hạng">
    @foreach ($types as $key => $info)
        <li class="nav-item">
            <a class="nav-link {{ $type === $key ? 'active' : '' }}"
               href="{{ route('ranking', ['loai' => $key, 'thoi-gian' => $key === 'danh-gia' ? null : $period]) }}">{{ $info['label'] }}</a>
        </li>
    @endforeach
</ul>

@if ($type !== 'danh-gia')
    <div class="btn-group btn-group-sm mb-4" role="group" aria-label="Khoảng thời gian">
        @foreach ($periods as $key => $info)
            <a class="btn {{ $period === $key ? 'btn-primary' : 'btn-outline-primary' }}"
               href="{{ route('ranking', ['loai' => $type, 'thoi-gian' => $key]) }}">{{ $info['label'] }}</a>
        @endforeach
    </div>
@else
    <p class="text-muted small mb-4">Chỉ tính truyện có từ 5 lượt đánh giá trở lên.</p>
@endif

@if ($rows === [])
    <div class="alert alert-light border">Chưa có dữ liệu cho bảng xếp hạng này. Bảng sẽ tự cập nhật khi có thêm lượt đọc.</div>
@else
    <ol class="list-unstyled ranking-list" aria-label="{{ $types[$type]['label'] }}">
        @foreach ($rows as $index => $row)
            @php
                $story = $row['story'];
                $rank = $index + 1;
            @endphp
            <li class="d-flex align-items-center gap-3 border-bottom py-2">
                <span class="ranking-position fw-bold text-center {{ $rank <= 3 ? 'text-danger fs-4' : 'text-muted fs-5' }}" style="width: 2.5rem">{{ $rank }}</span>
                <a href="{{ route('stories.show', $story) }}" class="flex-shrink-0">
                    <img src="{{ $story->cover_image ? Storage::url($story->cover_image) : asset('images/no-cover.jpg') }}"
                         alt="{{ $story->title }}" width="56" height="75" loading="lazy" style="object-fit: cover; border-radius: 4px"
                         onerror="this.onerror=null;this.src='{{ asset('images/no-cover.jpg') }}';">
                </a>
                <div class="flex-grow-1" style="min-width: 0">
                    <a href="{{ route('stories.show', $story) }}" class="fw-semibold text-decoration-none d-block text-truncate">{{ $story->title }}</a>
                    @if ($story->author_name)
                        <a href="{{ route('search', ['author' => $story->author_name]) }}" class="small text-muted text-decoration-none">{{ $story->author_name }}</a>
                    @endif
                    <div class="mt-1">
                        @foreach ($story->categories->take(2) as $cat)
                            <a href="{{ route('categories.show', $cat) }}" class="badge bg-light text-dark border text-decoration-none">{{ $cat->name }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="text-end flex-shrink-0">
                    <div class="fw-bold">{{ $type === 'danh-gia' ? number_format($row['score'], 1) : number_format($row['score']) }}</div>
                    <div class="small text-muted">{{ $types[$type]['unit'] }}@if ($type === 'danh-gia') ({{ $story->rating_count }} đánh giá)@endif</div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
@endsection
