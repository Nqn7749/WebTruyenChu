@extends('layouts.public')

@section('title', 'Xác nhận mật khẩu | Web Đọc Truyện Chữ')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-3">Xác nhận mật khẩu</h1>
                <p class="text-muted mb-4">
                    Đây là khu vực bảo mật. Vui lòng nhập lại mật khẩu để tiếp tục.
                </p>

                <form method="POST" action="{{ route('password.confirm') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu hiện tại</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required autocomplete="current-password" autofocus>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-jade w-100">Xác nhận</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
