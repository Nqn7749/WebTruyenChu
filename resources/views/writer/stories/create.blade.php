@extends('layouts.public')
@section('title', 'Đăng truyện mới')
@section('content')
<h1 class="h3 mb-3">Đăng truyện mới</h1>
<div class="alert alert-info">
    Truyện sẽ được công khai ngay, không cần admin duyệt. Bạn cần thêm ít nhất một chương trong vòng 30 ngày.
    Sau 7 ngày chưa có chương, Tủ truyện của tôi sẽ hiển thị cảnh báo.
</div>
<form method="POST" action="{{ route('writer.stories.store') }}" enctype="multipart/form-data">
    @csrf
    @include('writer.stories._form')
    <button class="btn btn-primary">Đăng truyện</button>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
