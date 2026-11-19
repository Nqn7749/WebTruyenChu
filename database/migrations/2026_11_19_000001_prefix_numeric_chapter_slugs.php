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
            ->select(['id', 'chapter_number', 'slug'])
            ->orderBy('id')
            ->chunkById(500, function ($chapters): void {
                foreach ($chapters as $chapter) {
                    if (! ctype_digit((string) $chapter->slug)) {
                        continue;
                    }

                    DB::table('chapters')
                        ->where('id', $chapter->id)
                        ->update([
                            'slug' => 'chuong-'.$chapter->chapter_number.'-'.$chapter->id,
                        ]);
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
