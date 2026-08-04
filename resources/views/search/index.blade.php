@extends('layouts.public')

@section('title', 'Tìm kiếm truyện')

@section('content')

<h4 class="mb-3">Kết quả tìm kiếm{{ $keyword ? ' cho "'.$keyword.'"' : '' }}</h4>

<form action="{{ route('search') }}" method="GET" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="search" name="q" class="form-control" placeholder="Nhập tên truyện..." value="{{ $keyword }}">
    </div>
    <div class="col-md-4">
        <select name="category" class="form-select">
            <option value="">-- Tất cả thể loại --</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->slug }}" {{ $categorySlug == $cat->slug ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Tìm kiếm</button>
    </div>
</form>

<div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
    @forelse ($stories as $story)
        <div class="col"><x-story-card :story="$story" /></div>
    @empty
        <p class="text-muted">Không tìm thấy truyện phù hợp.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $stories->links() }}
</div>

@endsection