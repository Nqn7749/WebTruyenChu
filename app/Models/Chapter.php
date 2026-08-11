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
        ];
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
