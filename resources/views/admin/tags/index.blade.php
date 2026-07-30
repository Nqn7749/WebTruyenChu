@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Quản lý thẻ tag</h4>
    <a href="{{ route('admin.tags.create') }}" class="btn btn-primary">+ Thêm tag</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>#</th>
            <th>Tên</th>
            <th>Số truyện</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($tags as $tag)
            <tr>
                <td>{{ $tag->id }}</td>
                <td>{{ $tag->name }}</td>
                <td>{{ $tag->stories_count }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.tags.edit', $tag) }}" class="btn btn-sm btn-warning">Sửa</a>
                    <form action="{{ route('admin.tags.destroy', $tag) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa tag này?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center">Chưa có tag nào.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $tags->links() }}
@endsection