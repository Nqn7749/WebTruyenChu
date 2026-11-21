<?php

namespace App\Models;

use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Chỉ các trường người dùng được phép nhập (đã qua validation).
     * Các trường hệ thống/quản trị (user_id, is_hot, is_featured,
     * is_recommended, status_publish) KHÔNG nằm ở đây — chỉ gán qua
     * forceFill() ở đúng chỗ có thẩm quyền (đăng truyện, duyệt truyện).
     */
    protected $fillable = [
        'title',
        'slug',
        'author_name',
        'cover_image',
        'description',
        'status',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    /**
     * Các cột cache/counter KHÔNG cho mass-assign qua request thông thường.
     * Chỉ update qua increment()/decrement() hoặc forceFill() nội bộ.
     * (views, reads_count, chapter_count, comment_count, favorite_count,
     *  rating_count, average_rating, last_chapter_at, last_view_at)
     */
    protected function casts(): array
    {
        return [
            'is_hot' => 'boolean',
            'is_featured' => 'boolean',
            'is_recommended' => 'boolean',
            'status_publish' => 'boolean',
            'last_chapter_at' => 'datetime',
            'last_view_at' => 'datetime',
            'average_rating' => 'decimal:2',
        ];
    }

    // ================= Relationships =================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'story_categories');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'story_tags');
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function readingHistories()
    {
        return $this->hasMany(ReadingHistory::class);
    }

    // ================= Route Key =================

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ================= Query Scopes =================

    /**
     * Chỉ lấy truyện đã publish và chưa bị soft-delete.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status_publish', true);
    }

    /**
     * Truyện hot: cờ is_hot = true, sắp theo lượt đọc thật giảm dần
     * (ở lại trang đủ 15 giây + cuộn qua 25% nội dung). Lượt xem làm
     * tiêu chí phụ: truyện chưa có lượt đọc thật (mới triển khai) vẫn
     * giữ thứ hạng cũ theo views thay vì rơi xuống cuối.
     */
    public function scopeHot(Builder $query): Builder
    {
        return $query->where('is_hot', true)
            ->orderByDesc('reads_count')
            ->orderByDesc('views');
    }

    /**
     * Truyện đề cử.
     */
    public function scopeRecommended(Builder $query): Builder
    {
        return $query->where('is_recommended', true)
            ->orderByDesc('last_chapter_at');
    }

    /**
     * Truyện mới cập nhật chương gần nhất.
     */
    public function scopeRecentlyUpdated(Builder $query): Builder
    {
        return $query->whereNotNull('last_chapter_at')
            ->orderByDesc('last_chapter_at');
    }

    /**
     * Truyện mới đăng lên hệ thống (theo created_at).
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Truyện đã hoàn thành.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->orderByDesc('last_chapter_at');
    }

    // ================= Helper (counter) =================

    public function incrementViews(): void
    {
        // Tăng view không phải cập nhật nội dung: đóng băng updated_at
        // để sitemap không tưởng truyện mới cập nhật (S-M1). Đặt ở đây
        // thay vì từng nơi gọi để mọi đường tăng view đều được hưởng.
        $this->timestamps = false;
        $this->increment('views', 1, ['last_view_at' => now()]);
        $this->timestamps = true;
    }

    /**
     * Thời điểm cập nhật nội dung gần nhất của truyện cho sitemap: hoặc
     * sửa truyện, hoặc đăng chương mới (S-L1). Chương mới không đổi
     * updated_at của truyện (refreshChapterStats đóng băng timestamps)
     * nên chỉ dùng updated_at sẽ làm lastmod đứng yên.
     */
    public function sitemapLastmod(): Carbon
    {
        $lastmod = $this->updated_at;

        if ($this->last_chapter_at && $this->last_chapter_at->greaterThan($lastmod)) {
            $lastmod = $this->last_chapter_at;
        }

        return $lastmod;
    }

    public function isFavoritedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->favorites()->where('user_id', $user->id)->exists();
    }
}
