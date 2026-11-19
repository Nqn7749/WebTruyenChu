<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Chỉ các trường người dùng được phép nhập (đã qua validation).
     * user_id (người viết, lấy từ tài khoản đang đăng nhập) và is_hidden
     * (do tác giả truyện/admin quyết định) KHÔNG nằm ở đây — chỉ gán qua
     * forceFill() ở đúng chỗ có thẩm quyền.
     */
    protected $fillable = [
        'story_id',
        'chapter_id',
        'parent_id',
        'content',
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
