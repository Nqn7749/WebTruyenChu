@extends('layouts.public')
@section('title', 'Thêm chương')
@section('content')
<h1 class="h3 mb-3">Thêm chương — {{ $story->title }}</h1>
<form method="POST" action="{{ route('writer.stories.chapters.store', $story) }}">
    @csrf
    @include('writer.chapters._form')
    <button class="btn btn-primary">Lưu chương</button>
    <a href="{{ route('writer.stories.chapters.index', $story) }}" class="btn btn-link">Quay lại</a>
</form>
@endsection
