<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'story_id',
        'chapter_id',
        'parent_id',
        'content',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function story()
    {
        return $this->belongsTo(Story::class)->withTrashed();
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class)->withTrashed();
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id')->withTrashed();
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
