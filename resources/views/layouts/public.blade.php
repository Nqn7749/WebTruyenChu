
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Web Đọc Truyện Chữ')</title>

    {{-- SEO cơ bản --}}
    <meta name="description" content="@yield('meta_description', 'Đọc truyện chữ online miễn phí, cập nhật nhanh, giao diện đẹp, không quảng cáo.')">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph (Facebook, Zalo) --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="Web Đọc Truyện Chữ">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('og_title', $__env->yieldContent('title', 'Web Đọc Truyện Chữ'))">
    <meta property="og:description" content="@yield('og_description', $__env->yieldContent('meta_description', 'Đọc truyện chữ online miễn phí, cập nhật nhanh.'))">
    <meta property="og:image" content="@yield('og_image', asset('images/no-cover.jpg'))">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=literata:400,500,600,700|be-vietnam-pro:400,500,600,700" rel="stylesheet">

    <link href="{{ asset('css/theme.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg app-navbar sticky-top py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <span class="brand-mark">読</span>
            <span class="brand-name">Đọc Truyện</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Thể loại</a>
                    <ul class="dropdown-menu">
                        @foreach ($navCategories ?? [] as $cat)
                            <li><a class="dropdown-item" href="{{ route('categories.show', $cat) }}">{{ $cat->name }}</a></li>
                        @endforeach
                    </ul>
                </li>
                @auth
                    <li class="nav-item"><a class="nav-link" href="{{ route('favorites.index') }}">Yêu thích</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('reading-history.index') }}">Lịch sử đọc</a></li>
                @endauth
            </ul>

            <form action="{{ route('search') }}" method="GET" class="d-flex me-3" style="max-width:320px;">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Tìm truyện..." value="{{ request('q') }}">
                <button class="btn btn-sm btn-search"><i class="bi bi-search"></i> Tìm kiếm</button>
            </form>

            <ul class="navbar-nav align-items-lg-center">
                @guest
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item">
                        <a class="btn btn-jade btn-sm ms-2 mt-1 mt-lg-0 nav-link" href="{{ route('register') }}">Đăng ký</a>
                    </li>

                @else
                    {{-- Chuông thông báo --}}
                    <li class="nav-item dropdown">

                        <a
                            class="nav-link position-relative"
                            href="#"
                            id="notificationBell"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <i class="bi bi-bell"></i>

                            <span
                                id="notificationBadge"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                                style="font-size: .6rem;"
                            >
                                0
                            </span>
                        </a>

                        <ul
                            id="notificationDropdown"
                            class="dropdown-menu dropdown-menu-end p-2"
                            style="
                                width: 340px;
                                max-height: 400px;
                                overflow-y: auto;
                            "
                        >

                            <li>
                                <span class="dropdown-item-text text-muted small">
                                    Đang tải thông báo...
                                </span>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a
                                    class="dropdown-item text-center small"
                                    href="{{ route('notifications.index') }}"
                                >
                                    Xem tất cả thông báo
                                </a>
                            </li>

                        </ul>
                    </li>

                    {{-- Dropdown tên user (giữ nguyên như cũ) --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Hồ sơ</a></li>
                            @if (auth()->user()->role?->slug === 'admin')
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Trang quản trị</a></li>
                            @endif
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="dropdown-item">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @endguest
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show"
             style="border-radius: var(--radius-sm); border: none; background: var(--jade-tint); color: var(--jade-dark);">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($favoriteFlash = session('favorite_flash'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2"
             style="border-radius: var(--radius-sm); border: none; background: var(--jade-tint); color: var(--jade-dark);">
            @if ($favoriteFlash['type'] === 'added')
                <span>Đã thêm vào truyện yêu thích. Bạn sẽ nhận thông báo khi có chương mới.</span>

                <form action="{{ route('favorites.toggle-notify', ['story' => $favoriteFlash['story_slug']]) }}"
                      method="POST" class="d-inline mb-0 flash-notify-form">
                    @csrf
                    @method('PATCH')

                    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline alert-link">
                        Tắt thông báo
                    </button>
                </form>
            @else
                <span>Đã bỏ yêu thích truyện.</span>
            @endif

            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

{{-- Toast dùng chung cho toàn bộ trang --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
    <div id="notifyToast" class="toast border-0" role="alert">
        <div class="d-flex">
            <div id="notifyToastMessage" class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<footer class="app-footer py-4 mt-5">
    <div class="container text-center small">
        &copy; {{ date('Y') }} Web Đọc Truyện Chữ. Đọc truyện mỗi ngày.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /*
    |--------------------------------------------------------------------------
    | Toast
    |--------------------------------------------------------------------------
    */

    const toastEl = document.getElementById('notifyToast');
    const toastMsg = document.getElementById('notifyToastMessage');

    let toast = null;

    if (toastEl && toastMsg) {
        toast = new bootstrap.Toast(toastEl, {
            delay: 3000
        });
    }

    function showToast(message, type = 'success') {

        if (!toastEl || !toastMsg) {
            return;
        }

        toastEl.classList.remove(
            'text-bg-success',
            'text-bg-warning',
            'text-bg-danger'
        );

        toastEl.classList.add(
            `text-bg-${type}`
        );

        toastMsg.textContent = message;

        toast?.show();
    }


    /*
    |--------------------------------------------------------------------------
    | Tắt thông báo yêu thích
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.flash-notify-form')
        .forEach(form => {

            form.addEventListener('submit', async event => {

                event.preventDefault();

                const button = form.querySelector('button');

                if (!button) {
                    return;
                }

                button.disabled = true;

                try {

                    const response = await fetch(
                        form.action,
                        {
                            method: 'POST',

                            headers: {
                                'X-CSRF-TOKEN':
                                    form.querySelector(
                                        '[name="_token"]'
                                    ).value,

                                'X-Requested-With':
                                    'XMLHttpRequest',

                                'Accept':
                                    'application/json'
                            },

                            body: new FormData(form)
                        }
                    );

                    const data =
                        await response.json();

                    if (
                        !response.ok ||
                        !data.success
                    ) {
                        throw new Error(
                            data.message ||
                            'Có lỗi xảy ra.'
                        );
                    }

                    form
                        .closest('.alert')
                        ?.remove();

                    showToast(
                        'Đã tắt thông báo chương mới.',
                        'warning'
                    );

                } catch (error) {

                    console.error(error);

                    showToast(
                        error.message ||
                        'Không thể cập nhật thông báo.',
                        'danger'
                    );

                } finally {

                    button.disabled = false;
                }
            });
        });


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION AJAX POLLING
    |--------------------------------------------------------------------------
    */

    @auth

    const notificationBadge =
        document.getElementById(
            'notificationBadge'
        );

    const notificationDropdown =
        document.getElementById(
            'notificationDropdown'
        );

    const notificationUnreadUrl =
        @json(route('notifications.unread'));

    let previousUnreadCount =
        parseInt(
            notificationBadge?.textContent || '0',
            10
        );


    /**
     * Escape HTML để tránh đưa nội dung notification
     * trực tiếp vào HTML mà không xử lý.
     */
    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }


    /**
     * Cập nhật số trên chuông.
     */
    function updateNotificationBadge(count) {

        if (!notificationBadge) {
            return;
        }

        count =
            Number.isFinite(Number(count))
                ? Number(count)
                : 0;

        if (count <= 0) {

            notificationBadge.classList.add(
                'd-none'
            );

            notificationBadge.textContent = '0';

            return;
        }

        notificationBadge.classList.remove(
            'd-none'
        );

        notificationBadge.textContent =
            count > 9
                ? '9+'
                : count;
    }


    /**
     * Render dropdown notification.
     */
    function renderNotifications(notifications) {

        if (!notificationDropdown) {
            return;
        }

        let html = '';

        if (
            !notifications ||
            notifications.length === 0
        ) {

            html += `
                <li>
                    <span
                        class="dropdown-item-text text-muted small"
                    >
                        Chưa có thông báo chưa đọc.
                    </span>
                </li>
            `;

        } else {

            notifications.forEach(notification => {

                const message =
                    escapeHtml(
                        notification.message
                    );

                const createdAt =
                    escapeHtml(
                        notification.created_at
                    );

                const url =
                    notification.mark_read_url
                        ? escapeHtml(notification.mark_read_url)
                        : '#';


                html += `
                    <li>
                        <form
                            action="${url}"
                            method="POST"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="{{ csrf_token() }}"
                            >

                            <input
                                type="hidden"
                                name="_method"
                                value="PATCH"
                            >

                            <button
                                type="submit"
                                class="dropdown-item small py-2 text-wrap"
                            >
                                <div class="fw-semibold">
                                    ${message}
                                </div>

                                <div
                                    class="text-muted mt-1"
                                    style="font-size: .72rem;"
                                >
                                    ${createdAt}

                                    <span class="badge bg-danger ms-1">
                                        Mới
                                    </span>
                                </div>
                            </button>
                        </form>
                    </li>
                `;
            });
        }


        html += `
            <li>
                <hr class="dropdown-divider">
            </li>

            <li>
                <a
                    class="dropdown-item text-center small"
                    href="{{ route('notifications.index') }}"
                >
                    Xem tất cả thông báo
                </a>
            </li>
        `;

        notificationDropdown.innerHTML =
            html;
    }


    /**
     * Kiểm tra notification mới.
     */
    async function checkNotifications(
        showNewNotification = true
    ) {

        try {

            const response =
                await fetch(
                    notificationUnreadUrl,
                    {
                        method: 'GET',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        cache: 'no-store'
                    }
                );


            if (!response.ok) {

                throw new Error(
                    `HTTP ${response.status}`
                );
            }


            const data =
                await response.json();


            if (!data.success) {
                return;
            }


            const unreadCount =
                Number(
                    data.unread_count || 0
                );


            /*
            |--------------------------------------------------------------------------
            | Update badge
            |--------------------------------------------------------------------------
            */

            updateNotificationBadge(
                unreadCount
            );


            /*
            |--------------------------------------------------------------------------
            | Nếu có notification mới
            |--------------------------------------------------------------------------
            */

            if (
                showNewNotification &&
                unreadCount > previousUnreadCount
            ) {

                const newCount =
                    unreadCount -
                    previousUnreadCount;


                showToast(
                    newCount === 1
                        ? 'Bạn có thông báo mới.'
                        : `Bạn có ${newCount} thông báo mới.`,
                    'success'
                );
            }


            previousUnreadCount =
                unreadCount;


            /*
            |--------------------------------------------------------------------------
            | Update dropdown
            |--------------------------------------------------------------------------
            */

            renderNotifications(
                data.notifications || []
            );

        } catch (error) {

            /*
             * Không hiển thị lỗi cho người dùng.
             * Polling lỗi không được làm ảnh hưởng
             * tới việc đọc truyện.
             */

            console.debug(
                'Không thể kiểm tra notification:',
                error
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Lần đầu tải trang
    |--------------------------------------------------------------------------
    */

    checkNotifications(false);


    /*
    |--------------------------------------------------------------------------
    | Polling mỗi 5 giây
    |--------------------------------------------------------------------------
    */

    const notificationPolling =
        setInterval(
            () => {
                checkNotifications(true);
            },
            5000
        );


    /*
    |--------------------------------------------------------------------------
    | Khi user click notification
    |--------------------------------------------------------------------------
    |
    | NotificationController sẽ markAsRead()
    | trước khi redirect.
    |
    */

    notificationDropdown
        ?.addEventListener(
            'click',
            event => {

                const notification =
                    event.target.closest(
                        '.notification-item'
                    );

                if (!notification) {
                    return;
                }

                /*
                 * Không cần tự gọi API mark-read ở đây.
                 * Link hiện tại sẽ đi qua NotificationController
                 * nếu bạn dùng route mark-read.
                 */
            }
        );


    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'beforeunload',
        () => {
            clearInterval(
                notificationPolling
            );
        }
    );

    @endauth
});


/*
|--------------------------------------------------------------------------
| Theme
|--------------------------------------------------------------------------
*/

document.documentElement.dataset.theme =
    localStorage.getItem('theme') ?? 'light';
</script>

@stack('scripts')

</body>
</html>