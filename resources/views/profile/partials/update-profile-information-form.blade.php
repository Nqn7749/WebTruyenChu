
<section>
    <header class="mb-3">
        <h5 class="mb-1">Thông tin hồ sơ</h5>
        <p class="text-muted small mb-0">
            Cập nhật tên và địa chỉ email của tài khoản.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-3">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label">Họ tên</label>
            <input id="name" name="name" type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="small text-muted mb-1">
                        Email của bạn chưa được xác minh.
                        <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                            Gửi lại email xác minh.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="small text-success mb-0">
                            Một liên kết xác minh mới đã được gửi đến email của bạn.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">Lưu</button>

            @if (session('status') === 'profile-updated')
                <span class="text-success small" id="profile-saved-msg">Đã lưu.</span>
            @endif
        </div>
    </form>
</section>

@if (session('status') === 'profile-updated')
<script>
    setTimeout(() => {
        const msg = document.getElementById('profile-saved-msg');
        if (msg) msg.style.display = 'none';
    }, 2000);
</script>
@endif
