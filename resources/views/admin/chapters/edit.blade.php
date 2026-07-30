@extends('layouts.admin')

@section('content')
<h4>Sửa chương — {{ $story->title }}</h4>

<form action="{{ route('admin.chapters.update', $chapter) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.chapters._form', ['chapterNumber' => $chapter->chapter_number])
    <button class="btn btn-primary">Cập nhật</button>
</form>
@endsection