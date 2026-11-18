@extends('layouts.admin')

@section('content')
<h4 class="mb-3">Nhật ký quản trị</h4>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Thời gian</th>
                <th>Quản trị viên</th>
                <th>Thao tác</th>
                <th>Nội dung</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->admin?->name ?? 'Tài khoản đã xóa' }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td>{{ $log->description }}</td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Chưa có nhật ký.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $logs->links() }}
@endsection
