<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class HomeCacheService
{
    private const RECENTLY_UPDATED_VERSION_KEY = 'home:recently_updated:version';

    /**
     * Invalidate homepage collections and advance the version used by
     * paginated recently-updated results.
     */
    public function invalidate(): void
    {
        foreach (['hot', 'recommended', 'newest', 'completed'] as $key) {
            Cache::forget("home:{$key}");
        }

        Cache::add(self::RECENTLY_UPDATED_VERSION_KEY, 1, now()->addYears(10));
        Cache::increment(self::RECENTLY_UPDATED_VERSION_KEY);
    }
}
