@extends('layouts.admin')

@section('content')
<h4>Sửa truyện</h4>

<form action="{{ route('admin.stories.update', $story) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.stories._form')
    <button class="btn btn-primary">Cập nhật</button>
</form>
@endsection