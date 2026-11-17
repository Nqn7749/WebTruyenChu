@extends('layouts.public')
@section('title', 'Đăng truyện mới')
@section('content')
<h1 class="h3 mb-3">Đăng truyện mới</h1>
<div class="alert alert-info">Truyện mới sẽ ở trạng thái chờ duyệt. Quản trị viên sẽ kiểm tra trước khi hiển thị công khai.</div>
<form method="POST" action="{{ route('writer.stories.store') }}" enctype="multipart/form-data">
    @csrf
    @include('writer.stories._form')
    <button class="btn btn-primary">Gửi truyện để duyệt</button>
    <a href="{{ route('writer.stories.index') }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
