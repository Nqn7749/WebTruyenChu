@extends('layouts.public')
@section('title', 'Sửa chương')
@section('content')
<h1 class="h3 mb-3">Sửa chương — {{ $story->title }}</h1>
<div class="alert alert-info">
    Bạn có thể chỉnh sửa nội dung và tự chọn đăng công khai hoặc lưu lại bản nháp.
    Nếu chương đang được đăng, bỏ chọn trạng thái công khai để đưa chương về bản nháp.
</div>
<form method="POST" action="{{ route('writer.chapters.update', $chapter) }}">
    @csrf
    @method('PUT')
    @include('writer.chapters._form')
    <button class="btn btn-primary">Lưu thay đổi</button>
    <a href="{{ route('writer.stories.chapters.index', $story) }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
