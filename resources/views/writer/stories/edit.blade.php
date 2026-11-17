@extends('layouts.public')
@section('title', 'Sửa truyện')
@section('content')
<h1 class="h3 mb-3">Sửa truyện: {{ $story->title }}</h1>
<div class="alert alert-warning">Sau khi chỉnh sửa, truyện sẽ được chuyển về trạng thái chờ duyệt và tạm ẩn khỏi danh sách công khai.</div>
<form method="POST" action="{{ route('writer.stories.update', $story) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('writer.stories._form')
    <button class="btn btn-primary">Lưu và gửi duyệt lại</button>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
