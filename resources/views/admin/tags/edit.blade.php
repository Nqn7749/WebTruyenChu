{{-- resources/views/admin/tags/edit.blade.php --}}
@extends('layouts.admin')

@section('content')
<h4>Sửa tag</h4>

<form action="{{ route('admin.tags.update', $tag) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.tags._form')
    <button class="btn btn-primary">Cập nhật</button>
</form>
@endsection