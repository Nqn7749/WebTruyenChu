@extends('layouts.public')

@section('title', 'Truyện yêu thích')

@section('content')
<div class="container">
    <h4 class="mb-3">Truyện yêu thích của bạn</h4>

    <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
        @forelse ($stories as $favorite)
            <div class="col d-flex flex-column">
                <x-story-card :story="$favorite->story" />

                <form action="{{ route('favorites.toggle-notify', $favorite->story) }}"
                      method="POST" class="mt-1 notify-form">
                    @csrf
                    @method('PATCH')

                    <button type="submit"
                            class="btn btn-sm w-100 notify-btn {{ $favorite->notify_new_chapter ? 'btn-outline-jade' : 'btn-outline-secondary' }}">
                        <i class="bi {{ $favorite->notify_new_chapter ? 'bi-bell-fill' : 'bi-bell-slash' }}"></i>
                        <span>{{ $favorite->notify_new_chapter ? 'Đang nhận thông báo' : 'Đã tắt thông báo' }}</span>
                    </button>
                </form>
            </div>
        @empty
            <p class="text-muted">
                Bạn chưa yêu thích truyện nào.
                <a href="{{ route('home') }}">Khám phá ngay</a>.
            </p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $stories->links() }}
    </div>
</div>

{{-- Toast --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
    <div id="notifyToast" class="toast border-0" role="alert">
        <div class="d-flex">
            <div id="notifyToastMessage" class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastEl = document.getElementById('notifyToast');
    const toastMsg = document.getElementById('notifyToastMessage');
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });

    function showToast(message, type = 'success') {
        toastEl.classList.remove('text-bg-success', 'text-bg-warning', 'text-bg-danger');
        toastEl.classList.add(`text-bg-${type}`);
        toastMsg.textContent = message;
        toast.show();
    }

    document.querySelectorAll('.notify-form').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();

            const button = form.querySelector('.notify-btn');
            const icon = button.querySelector('i');
            const text = button.querySelector('span');

            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Có lỗi xảy ra.');
                }

                if (data.notify_new_chapter) {
                    button.classList.replace('btn-outline-secondary', 'btn-outline-jade');
                    icon.classList.replace('bi-bell-slash', 'bi-bell-fill');
                    text.textContent = ' Đang nhận thông báo';
                    showToast('Đã bật thông báo chương mới.', 'success');
                } else {
                    button.classList.replace('btn-outline-jade', 'btn-outline-secondary');
                    icon.classList.replace('bi-bell-fill', 'bi-bell-slash');
                    text.textContent = ' Đã tắt thông báo';
                    showToast('Đã tắt thông báo chương mới.', 'warning');
                }
            } catch (error) {
                console.error(error);
                showToast(error.message || 'Không thể cập nhật thông báo.', 'danger');
            } finally {
                button.disabled = false;
            }
        });
    });
});
</script>
@endpush