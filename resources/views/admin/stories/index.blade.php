@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Quản lý truyện</h4>
</div>

<div class="alert alert-warning" role="alert">
    <strong>Lưu ý:</strong> Truyện mới do thành viên đăng mặc định CHƯA công khai, cần admin duyệt
    ở cột Thao tác. Khi xóa, bạn phải ghi rõ lý do. Truyện sẽ bị ẩn khỏi website và tác giả
    có thể xem lý do trong Tủ truyện của tôi. Hãy kiểm tra kỹ trước khi xác nhận.
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<table class="table table-bordered align-middle">
    <thead>
        <tr>
            <th>Ảnh</th>
            <th>Tên truyện</th>
            <th>Bút danh</th>
            <th>Người đăng</th>
            <th>Tình trạng</th>
            <th>Số chương</th>
            <th class="text-end">Thao tác</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($stories as $story)
            <tr>
                <td>
                    @if ($story->cover_image)
                        <img src="{{ Storage::url($story->cover_image) }}" width="50" alt="Ảnh bìa {{ $story->title }}">
                    @endif
                </td>
                <td>{{ $story->title }}</td>
                <td>{{ $story->author_name ?? '—' }}</td>
                <td>{{ $story->user?->name ?? '—' }}</td>
                <td>
                    <span class="badge bg-secondary">{{ $story->status }}</span>
                    @if ($story->status_publish)
                        <span class="badge bg-success">Đã công khai</span>
                    @else
                        <span class="badge bg-warning text-dark">Chờ duyệt</span>
                    @endif
                </td>
                <td>{{ $story->chapters_count }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-info">Chương</a>
                    <form action="{{ route('admin.stories.toggle-publish', $story) }}" method="POST" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm {{ $story->status_publish ? 'btn-outline-warning' : 'btn-success' }}">
                            {{ $story->status_publish ? 'Gỡ công khai' : 'Duyệt' }}
                        </button>
                    </form>
                    <form action="{{ route('admin.stories.destroy', $story) }}" method="POST" class="mt-2 text-start"
                          onsubmit="return confirm('CẢNH BÁO: Truyện sẽ bị xóa khỏi website. Bạn đã kiểm tra nội dung và nhập đúng lý do chưa?')">
                        @csrf
                        @method('DELETE')
                        <label for="deletion-reason-{{ $story->id }}" class="form-label small mb-1">Lý do xóa (bắt buộc)</label>
                        <textarea id="deletion-reason-{{ $story->id }}" name="deletion_reason" rows="2"
                                  minlength="10" maxlength="1000" required
                                  class="form-control form-control-sm @error('deletion_reason') is-invalid @enderror"
                                  placeholder="Nhập lý do cụ thể (ít nhất 10 ký tự)">{{ old('deletion_reason') }}</textarea>
                        @error('deletion_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <button class="btn btn-sm btn-danger mt-2">Xóa truyện</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center">Chưa có truyện nào.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $stories->links() }}
@endsection
