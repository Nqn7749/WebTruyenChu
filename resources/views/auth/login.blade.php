@extends('layouts.public')

@section('title', 'Đăng nhập | Web Đọc Truyện Chữ')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="auth-page auth-page--login">
    <section class="auth-card" aria-labelledby="login-title">
        <div class="auth-visual auth-visual--login">
            <a class="auth-brand" href="{{ route('home') }}" aria-label="Về trang chủ">
                <span class="brand-mark">読</span>
                <span>Đọc Truyện</span>
            </a>
            <div class="auth-visual-copy">
                <span class="auth-eyebrow">KHÔNG GIAN CỦA BẠN</span>
                <h2>Mỗi trang sách, một thế giới mới.</h2>
                <p>Đăng nhập để tiếp tục hành trình khám phá những câu chuyện yêu thích.</p>
                <div class="auth-book-stack" aria-hidden="true">
                    <span class="auth-book auth-book--one"></span>
                    <span class="auth-book auth-book--two"></span>
                    <span class="auth-book auth-book--three"></span>
                </div>
            </div>
            <span class="auth-visual-note">Đọc mỗi ngày · Mở rộng thế giới</span>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-heading">
                <span class="auth-eyebrow">CHÀO MỪNG TRỞ LẠI</span>
                <h1 id="login-title">Đăng nhập</h1>
                <p>Đăng nhập để lưu tiến độ và theo dõi truyện bạn yêu thích.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email hoặc tên đăng nhập</label>
                    <input id="email" class="form-control @error('email') is-invalid @enderror"
                           type="text" name="email" value="{{ old('email') }}" required autofocus
                           autocomplete="username" placeholder="Nhập email hoặc username">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input id="password" class="form-control @error('password') is-invalid @enderror"
                           type="password" name="password" required autocomplete="current-password"
                           placeholder="Nhập mật khẩu">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-form-options">
                    <label class="form-check">
                        <input id="remember_me" class="form-check-input" type="checkbox" name="remember">
                        <span class="form-check-label">Ghi nhớ đăng nhập</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
                    @endif
                </div>

                <button type="submit" class="btn btn-jade auth-submit">Đăng nhập <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="auth-switch">Chưa có tài khoản? <a href="{{ route('register') }}">Tạo tài khoản mới</a></p>
            <a class="auth-back-home" href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Quay về trang chủ</a>
        </div>
    </section>
</div>
@endsection
