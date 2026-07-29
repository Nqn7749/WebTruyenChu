@extends('layouts.admin')

@section('content')
<h4>Thêm truyện</h4>

<form action="{{ route('admin.stories.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.stories._form')
    <button class="btn btn-primary">Lưu</button>
</form>
@endsection