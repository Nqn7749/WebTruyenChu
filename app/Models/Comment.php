<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Chỉ các comment đang hiển thị trên giao diện: chưa ẩn, reply của
     * comment cha chưa ẩn/chưa xóa, và không thuộc chương đã xóa mềm
     * hoặc đã gỡ công khai (trang chương 404 khi status = false).
     * Mọi chỗ đếm comment (StoryStatsService::refreshStories,
     * refreshChapterComments) và mọi chỗ tăng/giảm tay
     * (StoryStatsService::countedCommentAmount) phải dùng chung định
     * nghĩa này, nếu không số đếm lệch dần với những gì người đọc
     * thấy (C-M1).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false)
            ->where(function (Builder $query) {
                $query->whereNull('parent_id')
                    // parent() có withTrashed nên phải loại cha đã xóa
                    // mềm tay ở đây.
                    ->orWhereHas('parent', fn (Builder $parent) => $parent
                        ->where('is_hidden', false)
                        ->whereNull('deleted_at'));
            })
            ->where(function (Builder $query) {
                $query->whereNull('chapter_id')
                    // chapter() có withTrashed nên phải loại chương đã
                    // xóa mềm tay ở đây, và loại luôn chương đã gỡ
                    // công khai (F10).
                    ->orWhereHas('chapter', fn (Builder $chapter) => $chapter
                        ->whereNull('deleted_at')
                        ->where('status', true));
            });
    }
}
