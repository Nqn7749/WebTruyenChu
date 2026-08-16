@extends('layouts.public')

@section('title', 'Thông báo')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Thông báo</h4>
    @if (auth()->user()->unreadNotifications()->exists())
        <form action="{{ route('notifications.mark-all-read') }}" method="POST">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-outline-jade">Đánh dấu tất cả đã đọc</button>
        </form>
    @endif
</div>

<div class="list-group">
    @forelse ($notifications as $notification)
        <form action="{{ route('notifications.mark-read', $notification->id) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit"
                class="list-group-item list-group-item-action text-start {{ $notification->read_at ? '' : 'fw-semibold' }}">
                {{ $notification->data['message'] }}
                <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
            </button>
        </form>
    @empty
        <p class="text-muted">Bạn chưa có thông báo nào.</p>
    @endforelse
</div>

<div class="mt-4">
    {{ $notifications->links() }}
</div>

@endsection