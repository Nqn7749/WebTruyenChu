<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị - Web Đọc Truyện Chữ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <nav class="bg-dark text-white p-3" style="width: 220px; min-height: 100vh;">
        <h5 class="mb-4">Admin Panel</h5>
        <ul class="nav nav-pills flex-column gap-1">
            <li><a href="{{ route('admin.dashboard') }}" class="nav-link text-white">Dashboard</a></li>
            <li><a href="{{ route('admin.categories.index') }}" class="nav-link text-white">Thể loại</a></li>
            <li><a href="{{ route('admin.stories.index') }}" class="nav-link text-white">Truyện</a></li>
            <li><a href="{{ route('admin.users.index') }}" class="nav-link text-white">Người dùng</a></li>
            <li><a href="{{ route('admin.comments.index') }}" class="nav-link text-white">Bình luận</a></li>
        </ul>
    </nav>

    <main class="flex-grow-1 p-4">
        @yield('content')
    </main>
</div>
</body>
</html>