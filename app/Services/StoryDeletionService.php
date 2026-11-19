<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoryDeletionService
{
    public function delete(
        Story $story,
        string $reason,
        ?int $deletedBy = null,
        bool $onlyWithoutChapters = false
    ): bool {
        $coverPath = null;

        $deleted = DB::transaction(function () use (
            $story,
            $reason,
            $deletedBy,
            $onlyWithoutChapters,
            &$coverPath
        ): bool {
            $lockedStory = Story::query()
                ->lockForUpdate()
                ->find($story->id);

            if (! $lockedStory) {
                return false;
            }

            if ($onlyWithoutChapters && $lockedStory->chapters()->exists()) {
                return false;
            }

            $coverPath = $lockedStory->cover_image;

            $lockedStory->forceFill([
                'deletion_reason' => $reason,
                'deleted_by' => $deletedBy,
            ])->save();

            $lockedStory->delete();

            return true;
        });

        if ($deleted && $coverPath) {
            // Defer filesystem side effects until the outermost DB transaction commits.
            DB::afterCommit(static function () use ($coverPath): void {
                Storage::disk('public')->delete($coverPath);
            });
        }

        return $deleted;
    }
}
