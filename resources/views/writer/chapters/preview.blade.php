@extends('layouts.public')
@section('title', 'Xem trước: '.$chapter->title)
@section('content')
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><strong>Chế độ xem trước.</strong> Chương này chưa được công khai; chỉ bạn mới xem được trang này.</span>
    <a href="{{ route('writer.chapters.edit', $chapter) }}" class="btn btn-sm btn-outline-dark">Chỉnh sửa chương</a>
</div>
<article class="mx-auto" style="max-width:800px">
    <div class="text-center mb-4">
        <div class="text-muted">{{ $story->title }}</div>
        <h1 class="h3">Chương {{ $chapter->chapter_number }}{{ $chapter->title ? ': '.$chapter->title : '' }}</h1>
        <div class="small text-muted">Bản xem trước · Không làm tăng lượt xem</div>
    </div>
    <div class="chapter-content" style="line-height:1.9">{!! $chapter->renderedContent() !!}</div>
</article>
@endsection
