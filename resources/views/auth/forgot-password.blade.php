@extends('layouts.public')

@section('title', 'Quên mật khẩu | Web Đọc Truyện Chữ')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-3">Quên mật khẩu?</h1>
                <p class="text-muted mb-4">
                    Nhập email đã đăng ký. Nếu tài khoản tồn tại, chúng tôi sẽ gửi liên kết
                    để bạn đặt lại mật khẩu.
                </p>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Địa chỉ email</label>
                        <input id="email" name="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required autofocus autocomplete="email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-jade w-100">Gửi liên kết đặt lại mật khẩu</button>
                </form>

                <p class="text-center mt-4 mb-0">
                    <a href="{{ route('login') }}">Quay lại đăng nhập</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
