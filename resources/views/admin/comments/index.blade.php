@extends('layouts.admin')

@section('content')
<h4>Kiểm duyệt bình luận</h4>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Người dùng</th>
            <th>Truyện</th>
            <th>Nội dung</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($comments as $comment)
            <tr>
                <td>{{ $comment->user->name }}</td>
                <td>{{ $comment->story->title }}</td>
                <td>{{ Str::limit($comment->content, 80) }}</td>
                <td>
                    <span class="badge {{ $comment->is_hidden ? 'bg-secondary' : 'bg-success' }}">
                        {{ $comment->is_hidden ? 'Đã ẩn' : 'Hiển thị' }}
                    </span>
                </td>
                <td class="text-end">
                    <form action="{{ route('admin.comments.toggle-hidden', $comment) }}" method="POST" class="d-inline">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-secondary">
                            {{ $comment->is_hidden ? 'Bỏ ẩn' : 'Ẩn' }}
                        </button>
                    </form>
                    <form action="{{ route('admin.comments.destroy', $comment) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Xóa bình luận này?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $comments->links() }}
@endsection