<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Ad extends Model
{
    /**
     * Where an ad can be shown: the left or right edge of a kind of page. The side rails only appear on
     * wide screens (see public/css/ads.css), where the page leaves empty space beside the content.
     */
    public const PLACEMENTS = [
        'home_left' => ['label' => 'Trang chủ — rìa trái', 'page' => 'home', 'side' => 'left'],
        'home_right' => ['label' => 'Trang chủ — rìa phải', 'page' => 'home', 'side' => 'right'],
        'story_left' => ['label' => 'Trang truyện — rìa trái', 'page' => 'story', 'side' => 'left'],
        'story_right' => ['label' => 'Trang truyện — rìa phải', 'page' => 'story', 'side' => 'right'],
        'chapter_left' => ['label' => 'Trang đọc chương — rìa trái', 'page' => 'chapter', 'side' => 'left'],
        'chapter_right' => ['label' => 'Trang đọc chương — rìa phải', 'page' => 'chapter', 'side' => 'right'],
        'list_left' => ['label' => 'Danh sách, tìm kiếm, thể loại, xếp hạng — rìa trái', 'page' => 'list', 'side' => 'left'],
        'list_right' => ['label' => 'Danh sách, tìm kiếm, thể loại, xếp hạng — rìa phải', 'page' => 'list', 'side' => 'right'],
    ];

    /** Ads stacked in one edge at most, so a rail never grows taller than the screen. */
    public const MAX_PER_PLACEMENT = 3;

    private const CACHE_MINUTES = 5;

    protected $fillable = [
        'name', 'placement', 'type', 'image_path', 'link_url', 'alt_text', 'html_code',
        'is_active', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Any change must show up on the site right away, not after the cache expires.
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    /**
     * Switched on and inside its schedule (open ended when a date is empty).
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Ads to show in one placement, in the order the admin set.
     *
     * @return Collection<int, Ad>
     */
    public static function forPlacement(string $placement): Collection
    {
        if (! array_key_exists($placement, self::PLACEMENTS)) {
            return (new static)->newCollection();
        }

        return Cache::remember(
            'ads:placement:'.$placement,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => static::query()
                ->running()
                ->where('placement', $placement)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(self::MAX_PER_PLACEMENT)
                ->get(),
        );
    }

    public static function flushCache(): void
    {
        foreach (array_keys(self::PLACEMENTS) as $placement) {
            Cache::forget('ads:placement:'.$placement);
        }
    }

    public function placementLabel(): string
    {
        return self::PLACEMENTS[$this->placement]['label'] ?? $this->placement;
    }

    /**
     * "running", "off", "scheduled" or "expired" for the admin list.
     */
    public function state(): string
    {
        if (! $this->is_active) {
            return 'off';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'running';
    }

    public function imageUrl(): ?string
    {
        // Same helper the story covers use: a path relative to the site ("/storage/..."), so the image loads
        // whatever host or port the site is served from (APP_URL is often not the dev server's address).
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
