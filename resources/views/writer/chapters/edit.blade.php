@extends('layouts.public')
@section('title', 'Sửa chương')
@section('content')
<h1 class="h3 mb-3">Sửa chương — {{ $story->title }}</h1>
<div class="alert alert-warning">Lưu chỉnh sửa sẽ chuyển chương về nháp để quản trị viên duyệt lại.</div>
<form method="POST" action="{{ route('writer.chapters.update', $chapter) }}">@csrf @method('PUT')
@include('writer.chapters._form')
<button class="btn btn-primary">Lưu chương nháp</button><a href="{{ route('writer.stories.chapters.index', $story) }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
