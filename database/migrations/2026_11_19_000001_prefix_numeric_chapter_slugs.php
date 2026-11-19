<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Numeric slugs conflict with the legacy chapter-number URL route.
     * Prefix them before slug-based chapter routes are enabled.
     */
    public function up(): void
    {
        DB::table('chapters')
            ->select(['id', 'story_id', 'chapter_number', 'slug'])
            ->orderBy('id')
            ->chunkById(500, function ($chapters): void {
                foreach ($chapters as $chapter) {
                    if (! ctype_digit((string) $chapter->slug)) {
                        continue;
                    }

                    $baseSlug = 'chuong-'.$chapter->chapter_number.'-'.$chapter->id;
                    $slug = $baseSlug;
                    $suffix = 1;

                    while (DB::table('chapters')
                        ->where('story_id', $chapter->story_id)
                        ->where('slug', $slug)
                        ->where('id', '!=', $chapter->id)
                        ->exists()) {
                        $slug = $baseSlug.'-'.$suffix++;
                    }

                    DB::table('chapters')
                        ->where('id', $chapter->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    /**
     * Slug normalization is intentionally irreversible; old numeric URLs
     * continue to resolve through the legacy redirect endpoint.
     */
    public function down(): void
    {
        // No rollback: restoring numeric slugs would reintroduce route ambiguity.
    }
};
