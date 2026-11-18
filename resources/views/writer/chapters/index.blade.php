@extends('layouts.public')
@section('title', 'Quản lý chương')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Chương truyện</h1><p class="text-muted mb-0">{{ $story->title }}</p></div><a href="{{ route('writer.stories.chapters.create', $story) }}" class="btn btn-primary">+ Thêm chương</a></div>
<div class="alert alert-info">Chương bạn thêm hoặc chỉnh sửa sẽ ở trạng thái nháp. Bạn có thể xem trước nội dung; bản xem trước không làm tăng lượt xem và chỉ chủ truyện truy cập được.</div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Số chương</th><th>Tiêu đề</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse ($chapters as $chapter)
<tr><td>{{ $chapter->chapter_number }}</td><td>{{ $chapter->title ?: 'Chưa đặt tiêu đề' }}</td><td><span class="badge {{ $chapter->status ? 'bg-success' : 'bg-secondary' }}">{{ $chapter->status ? 'Đã đăng' : 'Nháp / chờ duyệt' }}</span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-info" href="{{ route('writer.chapters.preview', $chapter) }}">Xem trước</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('writer.chapters.edit', $chapter) }}">Sửa</a><form class="d-inline" method="POST" action="{{ route('writer.chapters.destroy', $chapter) }}" onsubmit="return confirm('Xóa chương này?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr>
@empty
<tr><td colspan="4" class="text-center text-muted py-4">Chưa có chương nào.</td></tr>
@endforelse
</tbody></table></div>
{{ $chapters->links() }}<a href="{{ route('writer.stories.index') }}" class="btn btn-link px-0">← Về truyện của tôi</a>
@endsection
