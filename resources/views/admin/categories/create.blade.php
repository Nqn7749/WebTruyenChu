@extends('layouts.admin')

@section('content')
<h4>Thêm thể loại</h4>

<form action="{{ route('admin.categories.store') }}" method="POST">
    @csrf
    @include('admin.categories._form')
    <button class="btn btn-primary">Lưu</button>
</form>
@endsection