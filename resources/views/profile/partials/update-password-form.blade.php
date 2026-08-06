
<section>
    <header class="mb-3">
        <h5 class="mb-1">Đổi mật khẩu</h5>
        <p class="text-muted small mb-0">
            Sử dụng mật khẩu dài và ngẫu nhiên để bảo mật tài khoản.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-3">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">Mật khẩu hiện tại</label>
            <input id="update_password_current_password" name="current_password" type="password"
                   class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                   autocomplete="current-password">
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">Mật khẩu mới</label>
            <input id="update_password_password" name="password" type="password"
                   class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                   autocomplete="new-password">
            @error('password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">Xác nhận mật khẩu mới</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                   class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                   autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">Lưu</button>

            @if (session('status') === 'password-updated')
                <span class="text-success small" id="password-saved-msg">Đã lưu.</span>
            @endif
        </div>
    </form>
</section>

@if (session('status') === 'password-updated')
<script>
    setTimeout(() => {
        const msg = document.getElementById('password-saved-msg');
        if (msg) msg.style.display = 'none';
    }, 2000);
</script>
@endif
