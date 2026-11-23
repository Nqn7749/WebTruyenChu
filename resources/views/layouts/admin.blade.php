<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quản trị hệ thống - Web Đọc Truyện Chữ</title>

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('admin_theme');
                const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
                const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
                // Bootstrap 5.3 colors tables, forms, dropdowns, muted text... from data-bs-theme. Without it
                // they keep their light colors (dark text, white cells) on the dark admin background.
                document.documentElement.dataset.bsTheme = theme;
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.dataset.bsTheme = 'light';
            }
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="admin-body">

<div class="admin-wrapper">
    {{-- Sidebar SaaS --}}
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <span class="admin-brand-icon">読</span>
            <div>
                <div class="admin-brand-title">Đọc Truyện</div>
                <small class="text-muted" style="font-size: .72rem;">Bảng điều khiển</small>
            </div>
            <span class="admin-brand-badge">Admin</span>
        </div>

        <div class="px-3 pt-3">
            <a href="{{ route('home') }}" class="btn btn-outline-light btn-sm w-100 text-start d-flex align-items-center gap-2" style="font-size: .84rem; border-color: rgba(255,255,255,.15);">
                <i class="bi bi-arrow-left"></i>
                <span>Về trang chủ</span>
            </a>
        </div>

        <nav class="admin-sidebar-nav" aria-label="Menu quản trị">
            <div class="admin-nav-group-label">Tổng quan</div>
            <a href="{{ route('admin.dashboard') }}" class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="admin-nav-group-label">Quản lý nội dung</div>
            <a href="{{ route('admin.stories.index') }}" class="admin-nav-item {{ request()->routeIs('admin.stories.*') ? 'active' : '' }}">
                <i class="bi bi-book"></i>
                <span>Truyện</span>
            </a>
            <a href="{{ route('admin.story-import.index') }}" class="admin-nav-item {{ request()->routeIs('admin.story-import.*') ? 'active' : '' }}">
                <i class="bi bi-cloud-arrow-down"></i>
                <span>Nhập truyện từ URL</span>
            </a>
            <a href="{{ route('admin.categories.index') }}" class="admin-nav-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>Thể loại</span>
            </a>
            <a href="{{ route('admin.tags.index') }}" class="admin-nav-item {{ request()->routeIs('admin.tags.*') ? 'active' : '' }}">
                <i class="bi bi-tags"></i>
                <span>Thẻ tag</span>
            </a>

            <div class="admin-nav-group-label">Kiểm duyệt & Cộng đồng</div>
            <a href="{{ route('admin.comments.index') }}" class="admin-nav-item {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}">
                <i class="bi bi-chat-left-text"></i>
                <span>Bình luận</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="admin-nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                <i class="bi bi-shield-exclamation"></i>
                <span>Báo cáo cộng đồng</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="admin-nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>Người dùng</span>
            </a>

            <div class="admin-nav-group-label">Vận hành & Hệ thống</div>
            <a href="{{ route('admin.ads.index') }}" class="admin-nav-item {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
                <i class="bi bi-megaphone"></i>
                <span>Quảng cáo</span>
            </a>
            <a href="{{ route('admin.crawler.index') }}" class="admin-nav-item {{ request()->routeIs('admin.crawler.*') ? 'active' : '' }}">
                <i class="bi bi-robot"></i>
                <span>Crawler nguồn truyện</span>
            </a>
            <a href="{{ route('admin.activity-logs.index') }}" class="admin-nav-item {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                <span>Nhật ký quản trị</span>
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white fw-bold small flex-shrink-0" style="width: 32px; height: 32px;">
                    {{ mb_substr(auth()->user()->name, 0, 1, 'UTF-8') }}
                </div>
                <div class="text-truncate">
                    <div class="small fw-semibold text-white text-truncate">{{ auth()->user()->name }}</div>
                    <div class="text-muted" style="font-size: .7rem;">Quản trị viên</div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-link text-danger p-1" title="Đăng xuất" aria-label="Đăng xuất">
                    <i class="bi bi-box-arrow-right fs-5"></i>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Container --}}
    <div class="admin-main">
        {{-- Topbar --}}
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" aria-label="Mở menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="d-none d-sm-block text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i> Không gian quản trị an toàn
                </div>
            </div>

            <div class="admin-topbar-right">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Xem website</span>
                </a>

                <button type="button" id="adminThemeToggle" class="admin-theme-btn" title="Đổi giao diện Sáng / Tối" aria-label="Đổi giao diện">
                    <i class="bi bi-moon-stars" id="adminThemeIcon"></i>
                </button>

                <div class="dropdown">
                    <button class="btn btn-sm btn-link text-decoration-none dropdown-toggle text-body d-flex align-items-center gap-2 p-0" type="button" data-bs-toggle="dropdown">
                        <span class="d-none d-md-inline fw-semibold" style="color: var(--admin-text);">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Hồ sơ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        {{-- Content View --}}
        <main class="admin-content" id="adminMainContent">
            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    sidebarToggle?.addEventListener('click', () => {
        adminSidebar?.classList.toggle('show');
    });

    // Dark mode toggle
    const themeBtn = document.getElementById('adminThemeToggle');
    const themeIcon = document.getElementById('adminThemeIcon');

    function updateThemeIcon(theme) {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
        } else {
            themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
        }
    }

    const currentTheme = document.documentElement.dataset.theme || 'light';
    updateThemeIcon(currentTheme);

    themeBtn?.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        document.documentElement.dataset.bsTheme = nextTheme;
        localStorage.setItem('admin_theme', nextTheme);
        updateThemeIcon(nextTheme);
    });
});
</script>

@stack('scripts')
</body>
</html>
