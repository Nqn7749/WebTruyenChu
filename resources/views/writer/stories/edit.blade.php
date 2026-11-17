@extends('layouts.public')
@section('title', 'Sửa truyện')
@section('content')
<h1 class="h3 mb-3">Sửa truyện: {{ $story->title }}</h1>
<div class="alert alert-info">Chỉnh sửa thông tin không làm truyện chuyển về hàng chờ duyệt. Truyện vẫn được công khai.</div>
<form method="POST" action="{{ route('writer.stories.update', $story) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('writer.stories._form')
    <button class="btn btn-primary">Lưu thay đổi</button>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
