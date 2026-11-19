<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SitemapCacheService
{
    private const VERSION_KEY = 'sitemap:v3:version';

    /**
     * Return a versioned cache key. Old sitemap entries naturally expire by TTL
     * after invalidation, without needing to enumerate every cached page.
     */
    public function key(string $resource): string
    {
        return 'sitemap:v3:'.$this->version().':'.$resource;
    }

    public function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 1, now()->addYears(10));
        Cache::increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        Cache::add(self::VERSION_KEY, 1, now()->addYears(10));

        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
