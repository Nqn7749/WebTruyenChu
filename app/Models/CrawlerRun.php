<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerRun extends Model
{
    protected $fillable = [
        'crawler_source_id', 'started_by', 'status', 'stories_found',
        'stories_imported', 'chapters_imported', 'message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrawlerSource::class, 'crawler_source_id');
    }
}
