@extends('layouts.public')

@section('title', 'Đăng ký | Web Đọc Truyện Chữ')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="auth-page auth-page--register">
    <section class="auth-card auth-card--register" aria-labelledby="register-title">
        <div class="auth-visual auth-visual--register">
            <a class="auth-brand" href="{{ route('home') }}" aria-label="Về trang chủ">
                <span class="brand-mark">読</span>
                <span>Đọc Truyện</span>
            </a>
            <div class="auth-visual-copy">
                <span class="auth-eyebrow">BẮT ĐẦU HÀNH TRÌNH</span>
                <h2>Thế giới truyện đang chờ bạn.</h2>
                <p>Tạo tài khoản miễn phí để đánh dấu truyện, lưu lịch sử đọc và không bỏ lỡ chương mới.</p>
                <div class="auth-feature-list">
                    <span><i class="bi bi-bookmark-heart" aria-hidden="true"></i> Lưu truyện yêu thích</span>
                    <span><i class="bi bi-clock-history" aria-hidden="true"></i> Đồng bộ tiến độ đọc</span>
                    <span><i class="bi bi-bell" aria-hidden="true"></i> Theo dõi chương mới</span>
                </div>
            </div>
            <span class="auth-visual-note">Một tài khoản · Vô vàn câu chuyện</span>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-heading">
                <span class="auth-eyebrow">GIA NHẬP CỘNG ĐỒNG</span>
                <h1 id="register-title">Tạo tài khoản</h1>
                <p>Chỉ mất một phút để bắt đầu.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Họ và tên</label>
                    <input id="name" class="form-control @error('name') is-invalid @enderror"
                           type="text" name="name" value="{{ old('name') }}" required autofocus
                           autocomplete="name" placeholder="Tên hiển thị của bạn">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Tên đăng nhập</label>
                    <input id="username" class="form-control @error('username') is-invalid @enderror"
                           type="text" name="username" value="{{ old('username') }}" required
                           autocomplete="username" placeholder="Ví dụ: docgia123">
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Địa chỉ email</label>
                    <input id="email" class="form-control @error('email') is-invalid @enderror"
                           type="email" name="email" value="{{ old('email') }}" required
                           autocomplete="email" placeholder="ban@example.com">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <input id="password" class="form-control @error('password') is-invalid @enderror"
                               type="password" name="password" required autocomplete="new-password"
                               placeholder="Tạo mật khẩu">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password_confirmation" class="form-label">Nhập lại mật khẩu</label>
                        <input id="password_confirmation" class="form-control"
                               type="password" name="password_confirmation" required
                               autocomplete="new-password" placeholder="Nhập lại mật khẩu">
                    </div>
                </div>

                <button type="submit" class="btn btn-jade auth-submit">Tạo tài khoản <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="auth-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
            <a class="auth-back-home" href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Quay về trang chủ</a>
        </div>
    </section>
</div>
@endsection
