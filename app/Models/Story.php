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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status_publish', true);
    }

    public function scopeHot(Builder $query): Builder
    {
        return $query->where('is_hot', true)->orderByDesc('views');
    }

    public function scopeRecommended(Builder $query): Builder
    {
        return $query->where('is_recommended', true)->orderByDesc('last_chapter_at');
    }

    public function scopeRecentlyUpdated(Builder $query): Builder
    {
        return $query->whereNotNull('last_chapter_at')->orderByDesc('last_chapter_at');
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed')->orderByDesc('last_chapter_at');
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->whereFullText(['title', 'description'], $keyword);
    }

    public function incrementViews(): void
    {
        $this->increment('views', 1, ['last_view_at' => now()]);
    }

    public function isFavoritedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->favorites()->where('user_id', $user->id)->exists();
    }
}
