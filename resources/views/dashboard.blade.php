@extends('layouts.public')

@section('title', 'Bảng điều khiển | Web Đọc Truyện Chữ')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge text-bg-success mb-3">Tài khoản</span>
                <h1 class="h3 mb-2">Xin chào, {{ auth()->user()->name }}!</h1>
                <p class="text-muted mb-4">Bạn đã đăng nhập thành công. Tiếp tục hành trình đọc truyện của mình.</p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-person me-1" aria-hidden="true"></i> Hồ sơ cá nhân
                    </a>
                    <a href="{{ route('home') }}" class="btn btn-jade">
                        <i class="bi bi-book me-1" aria-hidden="true"></i> Khám phá truyện
                    </a>
                    @if (auth()->user()->hasVerifiedEmail())
                        <a href="{{ route('writer.dashboard') }}" class="btn btn-outline-success">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Khu vực sáng tác
                        </a>
                    @else
                        <a href="{{ route('verification.notice') }}" class="btn btn-outline-warning">
                            <i class="bi bi-envelope-check me-1" aria-hidden="true"></i> Xác minh email
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
