<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The comment UI supports root comments and one reply level.
     * Flatten any legacy deeper replies to their top-level ancestor.
     */
    public function up(): void
    {
        DB::table('comments')
            ->select(['id', 'parent_id'])
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->chunkById(500, function ($comments): void {
                foreach ($comments as $comment) {
                    $originalParentId = (int) $comment->parent_id;
                    $rootId = $originalParentId;
                    $visited = [(int) $comment->id => true];
                    $hasCycle = false;

                    while ($rootId) {
                        if (isset($visited[$rootId])) {
                            $hasCycle = true;
                            break;
                        }

                        $visited[$rootId] = true;
                        $parent = DB::table('comments')
                            ->where('id', $rootId)
                            ->first(['id', 'parent_id']);

                        if (! $parent || $parent->parent_id === null) {
                            break;
                        }

                        $rootId = (int) $parent->parent_id;
                    }

                    if (! $hasCycle && $rootId !== $originalParentId) {
                        DB::table('comments')
                            ->where('id', $comment->id)
                            ->update(['parent_id' => $rootId]);
                    }
                }
            });
    }

    /**
     * Flattening preserves all comments but intentionally discards old nesting depth.
     */
    public function down(): void
    {
        // No rollback: the previous parent-child depth cannot be reconstructed.
    }
};
