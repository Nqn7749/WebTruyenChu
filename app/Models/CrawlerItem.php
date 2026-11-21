<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerItem extends Model
{
    protected $fillable = ['crawler_source_id', 'external_key', 'story_id'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
