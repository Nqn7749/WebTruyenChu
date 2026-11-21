<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlerSource extends Model
{
    protected $fillable = [
        'name', 'base_url', 'listing_url', 'selectors',
        'permission_confirmed', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'selectors' => 'array',
            'permission_confirmed' => 'boolean',
            'enabled' => 'boolean',
            'last_crawled_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CrawlerRun::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrawlerItem::class);
    }
}
