{{-- resources/views/admin/tags/create.blade.php --}}
@extends('layouts.admin')

@section('content')
<h4>Thêm tag</h4>

<form action="{{ route('admin.tags.store') }}" method="POST">
    @csrf
    @include('admin.tags._form')
    <button class="btn btn-primary">Lưu</button>
</form>
@endsection