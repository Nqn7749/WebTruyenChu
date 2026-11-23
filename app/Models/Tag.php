<?php

namespace App\Models;

use App\Support\TagInput;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Find or create the tags typed by an author and return their ids.
     * Tags are shared and matched by slug, so "Hệ Thống" and "he thong" are the same tag.
     *
     * @return list<int>
     */
    public static function idsFromInput(?string $input): array
    {
        $ids = [];

        foreach (TagInput::parse($input) as $slug => $name) {
            try {
                $tag = static::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
            } catch (QueryException $exception) {
                // Another author created the same tag at the same moment (unique slug).
                $tag = static::query()->where('slug', $slug)->first() ?? throw $exception;
            }

            $ids[] = $tag->id;
        }

        return $ids;
    }

    public function stories()
    {
        return $this->belongsToMany(
            Story::class,
            'story_tags'
        );
    }
}
