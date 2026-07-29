@extends('layouts.admin')

@section('content')
<h4>Thêm chương — {{ $story->title }}</h4>

<form action="{{ route('admin.stories.chapters.store', $story) }}" method="POST">
    @csrf
    @include('admin.chapters._form', ['chapterNumber' => $nextNumber])
    <button class="btn btn-primary">Lưu</button>
</form>
@endsection