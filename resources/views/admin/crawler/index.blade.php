@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Crawler nguồn truyện</h1>
        <p class="text-muted mb-0">Chỉ thêm website bạn sở hữu hoặc đã được cấp quyền thu thập và tái sử dụng nội dung.</p>
    </div>
</div>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Thêm nguồn mới</h2>
        <form method="POST" action="{{ route('admin.crawler.store') }}">
            @csrf
            @include('admin.crawler.form', ['source' => null])
            <button class="btn btn-primary mt-3">Lưu nguồn</button>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Nguồn đã cấu hình</h2>
        @forelse ($sources as $source)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>{{ $source->name }}</strong>
                        <div class="small text-muted">{{ $source->base_url }}</div>
                        <div class="small mt-1">{{ $source->items_count }} truyện đã nhập · {{ $source->runs->count() }} lượt chạy gần đây</div>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <span class="badge {{ $source->enabled ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $source->enabled ? 'Đang bật' : 'Đang tắt' }}</span>
                        <form method="POST" action="{{ route('admin.crawler.run', $source) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" {{ ! $source->enabled || ! $source->permission_confirmed ? 'disabled' : '' }}>Chạy ngay</button>
                        </form>
                        <form method="POST" action="{{ route('admin.crawler.destroy', $source) }}" onsubmit="return confirm('Xóa nguồn crawler này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </div>
                </div>
                @if ($source->runs->isNotEmpty())
                    <div class="table-responsive mt-3">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Thời gian</th><th>Trạng thái</th><th>Truyện</th><th>Chương</th><th>Thông báo</th></tr></thead>
                            <tbody>
                            @foreach ($source->runs->take(5) as $run)
                                <tr>
                                    <td>{{ $run->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $run->status }}</td>
                                    <td>{{ $run->stories_imported }}/{{ $run->stories_found }}</td>
                                    <td>{{ $run->chapters_imported }}</td>
                                    <td class="small">{{ $run->message }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-muted mb-0">Chưa có nguồn nào.</p>
        @endforelse
        {{ $sources->links() }}
    </div>
</div>
@endsection
