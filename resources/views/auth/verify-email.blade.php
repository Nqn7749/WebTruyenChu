@extends('layouts.public')

@section('title', 'Xác minh email | Web Đọc Truyện Chữ')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="bi bi-envelope-check display-4 text-success" aria-hidden="true"></i>
                    <h1 class="h3 mt-3">Xác minh địa chỉ email</h1>
                </div>

                <p class="text-muted">
                    Cảm ơn bạn đã đăng ký. Hãy mở email và nhấn vào liên kết xác minh
                    chúng tôi đã gửi để sử dụng đầy đủ các tính năng dành cho tác giả.
                </p>

                @if (session('status') === 'verification-link-sent')
                    <div class="alert alert-success" role="status">
                        Một liên kết xác minh mới đã được gửi tới địa chỉ email của bạn.
                    </div>
                @endif

                @if ($errors->has('email'))
                    <div class="alert alert-danger" role="alert">{{ $errors->first('email') }}</div>
                @endif

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                    <form method="POST" action="{{ route('verification.send') }}" class="flex-grow-1">
                        @csrf
                        <button type="submit" class="btn btn-jade w-100">Gửi lại email xác minh</button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
