@extends('layouts.admin')

@section('content')
@if (!$stats['done'])
    <meta http-equiv="refresh" content="5">
@endif

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Tiến trình nhập truyện</h1>
        <p class="text-muted mb-0">{{ $batch->name }}</p>
    </div>
    <a href="{{ route('admin.story-import.index') }}" class="btn btn-outline-secondary">Nhập truyện khác</a>
</div>

@if (session('success'))
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
@endif

@if ($errors->has('retry'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('retry') }}</div>
@endif

@php
    $progress = $stats['progress'];
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <strong>
                @if ($batch->cancelled())
                    Đã hủy
                @elseif (!$stats['done'])
                    Đang xử lý
                @elseif ($stats['failed'] > 0)
                    Hoàn tất có chương lỗi
                @else
                    Hoàn tất thành công
                @endif
            </strong>
            <span class="text-muted">{{ $progress }}%</span>
        </div>
        <div class="progress mb-3" role="progressbar" aria-label="Tiến trình nhập chương"
             aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height: 12px">
            <div class="progress-bar" style="width: {{ $progress }}%"></div>
        </div>

        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-semibold">{{ $batch->totalJobs }}</div>
                    <div class="small text-muted">Tổng số chương</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-semibold text-success">{{ $stats['succeeded'] }}</div>
                    <div class="small text-muted">Thành công</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-semibold text-danger">{{ $stats['failed'] }}</div>
                    <div class="small text-muted">Thất bại</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3">
                    <div class="fs-4 fw-semibold">{{ $stats['waiting'] }}</div>
                    <div class="small text-muted">Đang chờ</div>
                </div>
            </div>
        </div>

        @if (!$stats['done'])
            <p class="small text-muted mt-3 mb-0">Trang tự cập nhật sau mỗi 5 giây. Hãy giữ queue worker hoạt động để xử lý các chương đang chờ.</p>
        @elseif ($stats['failed'] === 0)
            <p class="text-success mt-3 mb-0">Tất cả chương đã được xử lý thành công. Truyện vẫn ở trạng thái bản nháp cho đến khi bạn công khai.</p>
        @else
            <p class="text-warning mt-3 mb-0">Batch đã kết thúc. Các chương thành công vẫn được giữ lại; hãy kiểm tra lỗi bên dưới. Bạn có thể thử lại các chương lỗi bằng nút bên dưới.</p>
            <form method="POST" action="{{ route('admin.story-import.retry', $batch->id) }}" class="mt-2">
                @csrf
                <button type="submit" class="btn btn-warning">Thử lại {{ $stats['failed'] }} chương lỗi</button>
            </form>
        @endif
    </div>
</div>

@if ($failures->isNotEmpty())
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Chi tiết chương thất bại</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Chương và lý do lỗi</th>
                        <th>Thời điểm</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($failures as $failure)
                        <tr>
                            <td><code>{{ $failure['uuid'] }}</code></td>
                            <td class="text-break">{{ $failure['message'] }}</td>
                            <td>{{ $failure['failed_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('admin.stories.index') }}" class="btn btn-primary">Quản lý truyện</a>
    <a href="{{ route('admin.story-import.index') }}" class="btn btn-outline-secondary">Quay lại nhập truyện</a>
</div>
@endsection
