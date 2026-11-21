<section>
    <header class="mb-3">
        <h5 class="mb-1">Thông tin hồ sơ</h5>
        <p class="text-muted small mb-0">
            Cập nhật tên và địa chỉ email của tài khoản.
        </p>
    </header>

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
        </div>

        <div class="mb-3">
            <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-muted">(bắt buộc khi đổi email)</span></label>
            <input id="current_password" name="current_password" type="password"
                   class="form-control @error('current_password') is-invalid @enderror"
                   autocomplete="current-password">
            @error('current_password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Chỉ cần nhập khi bạn đổi địa chỉ email.</div>
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
