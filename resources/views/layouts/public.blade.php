<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Web Đọc Truyện Chữ')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .navbar-brand { font-weight: 700; }
        .story-cover { width: 100%; aspect-ratio: 3/4; object-fit: cover; border-radius: .5rem; }
        .chapter-list-item:hover { background: #f1f3f5; }
    </style>
    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">📖 Đọc Truyện</a>
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

            <form action="{{ route('search') }}" method="GET" class="d-flex me-3">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Tìm truyện..." value="{{ request('q') }}">
                <button class="btn btn-sm btn-outline-light ms-2">Tìm</button>
            </form>

            <ul class="navbar-nav">
                @guest
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Đăng ký</a></li>
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
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<footer class="bg-dark text-white-50 py-4 mt-5">
    <div class="container text-center small">
        &copy; {{ date('Y') }} Web Đọc Truyện Chữ.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>