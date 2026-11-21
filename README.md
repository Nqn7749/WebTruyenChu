# Web Đọc Truyện Chữ

Nền tảng đọc truyện chữ online (tiếng Việt) xây dựng bằng Laravel 12. Độc giả đọc, theo dõi, đánh giá và bình luận; tác giả tự đăng truyện/chương; quản trị viên kiểm duyệt cộng đồng.

## Tính năng

**Độc giả**
- Trang chủ: truyện hot, đề cử, mới cập nhật, mới đăng, đã hoàn thành
- Tìm kiếm (tên, tác giả, mô tả, thể loại, tag), lọc theo trạng thái/thể loại/số chương, sắp xếp
- Trình đọc: chỉnh cỡ chữ, giãn dòng, giao diện sáng/sepia/tối, chế độ tập trung, nhớ vị trí đọc
- Yêu thích, thông báo chương mới, lịch sử đọc (đồng bộ % tiến độ), đánh giá 1–5 sao
- Bình luận truyện/chương (1 cấp trả lời), báo cáo nội dung vi phạm
- SEO: URL slug, meta/Open Graph, sitemap XML (phân trang)

**Tác giả** (`/sang-tac`, yêu cầu email đã xác minh)
- Đăng/sửa/xóa truyện, quản lý chương (bản nháp / đăng công khai, xem trước)
- Ẩn/hiện bình luận trên truyện của mình, dashboard thống kê 30 ngày

**Quản trị** (`/admin`, role `admin`)
- Thể loại (phân cấp), tag, người dùng (khóa/đổi vai trò), truyện (duyệt/gỡ/xóa kèm lý do)
- Kiểm duyệt bình luận, xử lý báo cáo cộng đồng, nhật ký thao tác quản trị

## Công nghệ

| Thành phần | Chi tiết |
|---|---|
| Backend | Laravel 12, PHP 8.2+ (CI chạy 8.3) |
| Frontend | Blade + Bootstrap 5 (CDN), `public/css/theme.css`, `experience-refresh.css` |
| Auth | Laravel Breeze (đăng nhập bằng email hoặc username) |
| CSDL | MySQL (production), SQLite (test nhanh) |
| Cache / Queue / Session | `database` driver mặc định |
| Test | Pest + PHPUnit, Pint (code style) |

## Cài đặt nhanh

```bash
git clone <repo-url> && cd <repo>
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Cấu hình `.env` (MySQL):

```env
DB_CONNECTION=mysql
DB_DATABASE=webtruyenchu
DB_USERNAME=...
DB_PASSWORD=...
APP_URL=http://127.0.0.1:8000
```

Khởi tạo dữ liệu:

```bash
php artisan migrate
php artisan db:seed            # production: chỉ tạo role; local/testing: thêm dữ liệu demo
php artisan storage:link       # bắt buộc để hiển thị ảnh bìa
php artisan app:create-admin   # tạo tài khoản quản trị tương tác
```

Chạy ứng dụng:

```bash
php artisan serve              # http://127.0.0.1:8000
php artisan queue:work --timeout=1800  # gửi thông báo chương mới (NewChapterPublished)
php artisan schedule:work      # chạy tác vụ định kỳ khi phát triển
```

## Tác vụ định kỳ

| Lệnh | Lịch | Mô tả |
|---|---|---|
| `stories:delete-without-chapters` | 02:00 hằng ngày | Xóa mềm truyện không có chương sau 30 ngày |

Production cần cron: `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

## Cấu trúc thư mục chính

```
app/Http/Controllers/          # Controller công khai
app/Http/Controllers/Admin/    # Khu quản trị
app/Http/Controllers/Writer/   # Khu tác giả
app/Http/Requests/             # Form Request
app/Observers/                 # Story/Chapter/Category: xóa cache, sitemap
app/Services/                  # StoryStatsService, StoryDeletionService, SitemapCacheService, ...
routes/web.php | admin.php | auth.php
resources/views/               # layouts.public, layouts.admin, admin/, writer/, ...
```

## Quy ước quan trọng

- Các trường hệ thống (`role_id`, `status`, `status_publish`, counter…) **không** nằm trong `$fillable`; gán bằng `forceFill()` ở nơi có thẩm quyền.
- URL chương dùng slug: `/truyen/{story}/chuong/{slug}`. URL cũ theo số chương được 301 về URL slug.
- Counter (`chapter_count`, `comment_count`, …) được tính lại qua `StoryStatsService`.
- Cache trang chủ/sitemap bị xóa bởi Observer; sitemap dùng cache key có phiên bản.

## Kiểm thử

```bash
php artisan test
vendor/bin/pint --test
php artisan test --filter=MySqlFullTextSearchTest   # cần MySQL
```

## Triển khai

Xem [docs/production-readiness.md](docs/production-readiness.md).
