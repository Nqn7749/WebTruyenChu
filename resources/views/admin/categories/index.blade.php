@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Quản lý thể loại</h4>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">+ Thêm thể loại</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>#</th>
            <th>Tên</th>
            <th>Danh mục cha</th>
            <th>Số truyện</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($categories as $category)
            <tr>
                <td>{{ $category->id }}</td>
                <td>{{ $category->name }}</td>
                <td>{{ $category->parent?->name ?? '—' }}</td>
                <td>{{ $category->stories_count }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-warning">Sửa</a>
                    <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa thể loại này?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">Chưa có thể loại nào.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $categories->links() }}
@endsection