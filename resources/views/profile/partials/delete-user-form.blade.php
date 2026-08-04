<section>
    <header class="mb-3">
        <h5 class="mb-1 text-danger">Xóa tài khoản</h5>
        <p class="text-muted small mb-0">
            Sau khi tài khoản bị xóa, toàn bộ dữ liệu liên quan sẽ bị xóa vĩnh viễn.
            Vui lòng tải xuống mọi dữ liệu bạn muốn giữ lại trước khi xóa tài khoản.
        </p>
    </header>

    <button type="button" class="btn btn-danger mt-3" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
        Xóa tài khoản
    </button>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true"
         @if ($errors->userDeletion->isNotEmpty()) data-bs-show="true" @endif>
        <div class="modal-dialog">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Bạn có chắc chắn muốn xóa tài khoản?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Vui lòng nhập mật khẩu để xác nhận xóa tài khoản vĩnh viễn.
                        </p>

                        <label for="password" class="form-label">Mật khẩu</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                               placeholder="Mật khẩu">
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger">Xóa tài khoản</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

@if ($errors->userDeletion->isNotEmpty())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        modal.show();
    });
</script>
@endif