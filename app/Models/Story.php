<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'author_name',
        'cover_image',
        'description',
        'status',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_hot',
        'is_featured',
        'is_recommended',
        'status_publish',
    ];

    /**
     * Các cột cache/counter KHÔNG cho mass-assign qua request thông thường.
     * Chỉ update qua increment()/decrement() hoặc forceFill() nội bộ.
     * (views, chapter_count, comment_count, favorite_count,
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

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
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
     * Truyện hot: cờ is_hot = true, sắp theo views giảm dần.
     */
    public function scopeHot(Builder $query): Builder
    {
        return $query->where('is_hot', true)
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
     * Tìm kiếm full-text theo title/description.
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->whereFullText(['title', 'description'], $keyword);
    }

    // ================= Helper (counter) =================

    public function incrementViews(): void
    {
        $this->increment('views');
        $this->update(['last_view_at' => now()]);
    }
}