<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Không tìm thấy trang | Web Đọc Truyện Chữ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container text-center py-5">
        <h1 class="display-1 fw-bold text-muted">404</h1>
        <h2 class="mb-3">Không tìm thấy trang</h2>
        <p class="text-muted mb-4">Trang bạn tìm có thể đã bị xóa, đổi tên hoặc chưa từng tồn tại.</p>
        <a href="{{ route('home') }}" class="btn btn-primary">Về trang chủ</a>
        <a href="{{ route('stories.index') }}" class="btn btn-outline-secondary ms-2">Danh sách truyện</a>
    </div>
</body>
</html>
