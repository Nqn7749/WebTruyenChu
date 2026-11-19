<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    /** @use HasFactory<\Database\Factories\ChapterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'story_id',
        'chapter_number',
        'title',
        'slug',
        'content',
        'views',
        'comment_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Chapter $chapter): void {
            if ($chapter->status && ! $chapter->published_at) {
                $chapter->published_at = now();
            }
        });

        static::updating(function (Chapter $chapter): void {
            if (! $chapter->isDirty('status')) {
                return;
            }

            $chapter->published_at = $chapter->status ? now() : null;
        });
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
