<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingHistory extends Model
{
    /** @use HasFactory<\Database\Factories\ReadingHistoryFactory> */
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'story_id',
        'chapter_id',
        'read_at',
        'scroll_percent',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

}
