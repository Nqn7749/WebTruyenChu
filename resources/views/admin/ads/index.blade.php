@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Quản lý quảng cáo</h1>
        <p class="text-muted small mb-0">Quảng cáo hiện ở rìa trái và rìa phải của trang trên màn hình rộng (từ 1720px), đồng thời tối ưu hiển thị dạng banner tài trợ an toàn trên Mobile & Laptop.</p>
    </div>
    <a href="{{ route('admin.ads.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Thêm quảng cáo
    </a>
</div>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="bi bi-layout-sidebar"></i></span>
            <h2 class="h6 fw-bold mb-0">Các vị trí đặt quảng cáo</h2>
        </div>
        <p class="small text-muted mb-3">Mỗi vị trí hiện tối đa {{ $maxPerPlacement }} quảng cáo đang chạy (theo thứ tự ưu tiên). Các quảng cáo cùng rìa chia đều chiều cao. Ảnh <strong>cỡ nào cũng được</strong> (tối đa 1 MB): trên màn hình rộng (≥ 1720px), quảng cáo rộng 160 px; trên Mobile & Laptop (< 1720px), quảng cáo tự động hiển thị dạng banner an toàn ở chân trang đọc và trước danh sách chương, không quấy rầy người đọc.</p>
        <div class="row g-2">
            @foreach ($placements as $key => $info)
                <div class="col-12 col-md-6">
                    <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2 bg-light-subtle">
                        <span class="fw-medium small">{{ $info['label'] }}</span>
                        <span class="badge {{ ($running[$key] ?? 0) > 0 ? 'bg-success' : 'bg-secondary' }} rounded-pill">{{ $running[$key] ?? 0 }} đang chạy</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted text-uppercase">
                        <th>QUẢNG CÁO</th>
                        <th>VỊ TRÍ</th>
                        <th>TRẠNG THÁI</th>
                        <th>LỊCH CHẠY</th>
                        <th class="text-end">LƯỢT BẤM</th>
                        <th class="text-end">THAO TÁC</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($ads as $ad)
                    @php
                        $state = $ad->state();
                        $badge = ['running' => ['bg-success', 'Đang chạy'], 'off' => ['bg-secondary', 'Đã tắt'], 'scheduled' => ['bg-info text-dark', 'Chưa tới giờ'], 'expired' => ['bg-warning text-dark', 'Hết hạn']][$state];
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                @if ($ad->type === 'image' && $ad->image_path)
                                    <img src="{{ $ad->imageUrl() }}" alt="" width="48" height="64" class="rounded border shadow-sm" style="object-fit: cover;">
                                @else
                                    <span class="badge bg-dark rounded-3 px-2 py-3"><i class="bi bi-code-slash d-block"></i> Mã</span>
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $ad->name }}</div>
                                    @if ($ad->link_url)
                                        <div class="small text-muted text-truncate" style="max-width: 260px">
                                            <a href="{{ $ad->link_url }}" target="_blank" rel="noopener" class="text-muted text-decoration-none">
                                                <i class="bi bi-link-45deg"></i> {{ $ad->link_url }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <span class="fw-medium">{{ $ad->placementLabel() }}</span>
                            <div class="text-muted mt-1"><i class="bi bi-sort-numeric-down me-1"></i> Ưu tiên: {{ $ad->sort_order }}</div>
                        </td>
                        <td><span class="badge {{ $badge[0] }} rounded-pill">{{ $badge[1] }}</span></td>
                        <td class="small text-muted">
                            <div><i class="bi bi-play-circle me-1"></i> {{ $ad->starts_at ? $ad->starts_at->format('d/m/Y H:i') : 'Ngay' }}</div>
                            <div><i class="bi bi-stop-circle me-1"></i> {{ $ad->ends_at ? $ad->ends_at->format('d/m/Y H:i') : 'Không giới hạn' }}</div>
                        </td>
                        <td class="text-end fw-bold">
                            @if ($ad->type === 'image')
                                <span class="badge bg-light text-dark border">{{ number_format($ad->clicks) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <form action="{{ route('admin.ads.toggle', $ad) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $ad->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $ad->is_active ? 'Tắt quảng cáo' : 'Bật quảng cáo' }}">
                                        {{ $ad->is_active ? 'Tắt' : 'Bật' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.ads.edit', $ad) }}" class="btn btn-sm btn-outline-primary" title="Sửa">
                                    Sửa
                                </a>
                                <form action="{{ route('admin.ads.destroy', $ad) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa quảng cáo này?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Xóa">
                                        Xóa
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">Chưa có quảng cáo nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $ads->links() }}</div>
    </div>
</div>
@endsection
