<?php

namespace App\Models;

use App\Support\ChapterContent;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Chỉ các trường được phép nhập (đã qua validation). `status` là cờ
     * xuất bản do chính chủ truyện/admin chọn nên giữ lại. Các counter
     * (views, comment_count) KHÔNG nằm ở đây — chỉ update qua
     * increment()/decrement() hoặc forceFill() nội bộ.
     */
    protected $fillable = [
        'story_id',
        'chapter_number',
        'title',
        'slug',
        'content',
        'status',
    ];

    /**
     * Rich-text content is sanitized on every write (admin, writer, importer, crawler),
     * so stored HTML can only contain the allowlisted tags.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : ChapterContent::sanitize($value),
        );
    }

    /**
     * Safe HTML for display (also handles legacy plain-text chapters).
     */
    public function renderedContent(): string
    {
        // Memoized per instance and content: the reader page needs it for both the
        // meta description and the body, and purifying is not free.
        $key = md5((string) $this->content);
        if (($this->renderedContentCache[0] ?? null) !== $key) {
            $this->renderedContentCache = [$key, ChapterContent::toHtml($this->content)];
        }

        return $this->renderedContentCache[1];
    }

    /** @var array{0: string, 1: string}|null */
    private ?array $renderedContentCache = null;

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
            if (($chapter->status ?? true) && ! $chapter->published_at) {
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
