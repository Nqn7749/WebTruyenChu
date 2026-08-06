
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Web Đọc Truyện Chữ')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

            <ul class="navbar-nav">
                @guest
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item">
                        <a class="btn btn-jade btn-sm ms-2 mt-1 mt-lg-0" href="{{ route('register') }}">Đăng ký</a>
                    </li>
                    <li class="nav-item">
                        <button id="themeToggle"
                                class="btn btn-outline-light btn-sm">
                            🌙
                        </button>
                    </li>
                @else
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
        <div class="alert alert-success alert-dismissible fade show" style="border-radius: var(--radius-sm); border: none; background: var(--jade-tint); color: var(--jade-dark);">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<footer class="app-footer py-4 mt-5">
    <div class="container text-center small">
        &copy; {{ date('Y') }} Web Đọc Truyện Chữ. Đọc truyện mỗi ngày.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
@stack('scripts')
<script>
    document.documentElement.dataset.theme = localStorage.getItem('theme') ?? 'light';
</script>

</body>
</html> 
