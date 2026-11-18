@extends('layouts.admin')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">Báo cáo cộng đồng</h4>
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm" aria-label="Trạng thái báo cáo">
            @foreach (['pending' => 'Chờ xử lý', 'reviewed' => 'Đã xem', 'resolved' => 'Đã xử lý', 'dismissed' => 'Đã từ chối'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary">Lọc</button>
    </form>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@forelse ($reports as $report)
    <article class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <strong>#{{ $report->id }} · {{ $report->reportable_type === \App\Models\Story::class ? 'Truyện' : 'Bình luận' }}</strong>
                <span class="badge text-bg-secondary">{{ $report->reason }}</span>
            </div>
            <p class="mb-1 mt-2">Người báo cáo: {{ $report->reporter?->name ?? 'Tài khoản đã xóa' }}</p>
            <p class="mb-1">Đối tượng:
                @if ($report->reportable instanceof \App\Models\Story)
                    {{ $report->reportable->title }}
                @elseif ($report->reportable instanceof \App\Models\Comment)
                    {{ Str::limit($report->reportable->content, 180) }}
                @else
                    Nội dung không còn tồn tại
                @endif
            </p>
            @if ($report->details)
                <p class="text-muted mb-2">{{ $report->details }}</p>
            @endif
            @if ($report->status === 'pending')
                <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="row g-2 align-items-end mt-2">
                    @csrf
                    @method('PATCH')
                    <div class="col-md-3">
                        <label class="form-label">Hành động</label>
                        <select name="action" class="form-select" required>
                            <option value="none">Từ chối báo cáo</option>
                            @if ($report->reportable instanceof \App\Models\Comment)
                                <option value="hide_comment">Ẩn bình luận</option>
                            @elseif ($report->reportable instanceof \App\Models\Story)
                                <option value="delete_story">Xóa truyện</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Ghi chú xử lý / lý do</label>
                        <input name="resolution_note" class="form-control" minlength="5" maxlength="1000" required>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Xác nhận</button>
                    </div>
                </form>
            @else
                <p class="small text-muted mb-0">Xử lý bởi {{ $report->reviewer?->name ?? 'Quản trị viên' }} · {{ $report->resolution_note }}</p>
            @endif
        </div>
    </article>
@empty
    <p class="text-muted">Không có báo cáo trong trạng thái này.</p>
@endforelse

{{ $reports->links() }}
@endsection
