<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'views',
        'chapter_count',
        'comment_count',
        'favorite_count',
        'rating_count',
        'average_rating',
        'is_hot',
        'is_featured',
        'is_recommended',
        'status_publish',
        'last_chapter_at',
        'last_view_at',
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
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'story_categories'
        );
    }

    public function tags()
    {
        return $this->belongsToMany(
            Tag::class,
            'story_tags'
        );
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
