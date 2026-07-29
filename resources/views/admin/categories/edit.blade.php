    @extends('layouts.admin')

@section('content')
<h4>Sửa thể loại</h4>

<form action="{{ route('admin.categories.update', $category) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.categories._form')
    <button class="btn btn-primary">Cập nhật</button>
</form>
@endsection