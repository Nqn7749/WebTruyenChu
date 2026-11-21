<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoryDeletionService
{
    /**
     * Xóa mềm truyện (soft-delete) và ghi lý do/người xóa.
     *
     * Ảnh bìa được GIỮ LẠI cố ý: soft-delete nghĩa là truyện vẫn có thể
     * khôi phục, xóa file bìa lúc này sẽ khiến truyện khôi phục bị mất bìa
     * vĩnh viễn. Chỉ xóa file bìa khi xóa vĩnh viễn, xem forceDelete().
     */
    public function delete(
        Story $story,
        string $reason,
        ?int $deletedBy = null,
        bool $onlyWithoutChapters = false
    ): bool {
        return DB::transaction(function () use (
            $story,
            $reason,
            $deletedBy,
            $onlyWithoutChapters
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

            $lockedStory->forceFill([
                'deletion_reason' => $reason,
                'deleted_by' => $deletedBy,
            ])->save();

            $lockedStory->delete();

            return true;
        });
    }

    /**
     * Xóa vĩnh viễn truyện đã xóa mềm (hoặc chưa xóa) và xóa luôn file ảnh bìa.
     * Đây là nơi duy nhất được phép xóa file bìa.
     */
    public function forceDelete(
        Story $story,
        string $reason,
        ?int $deletedBy = null
    ): bool {
        $coverPath = null;

        $deleted = DB::transaction(function () use (
            $story,
            $reason,
            $deletedBy,
            &$coverPath
        ): bool {
            $lockedStory = Story::query()
                ->withTrashed()
                ->lockForUpdate()
                ->find($story->id);

            if (! $lockedStory) {
                return false;
            }

            $coverPath = $lockedStory->cover_image;

            $lockedStory->forceFill([
                'deletion_reason' => $reason,
                'deleted_by' => $deletedBy,
            ])->save();

            $lockedStory->forceDelete();

            // Thông báo về truyện này (kể cả thông báo chương của nó,
            // vì payload có story_id) không còn mở được nữa (P-M2).
            DB::table('notifications')
                ->whereJsonContains('data->story_id', $lockedStory->id)
                ->delete();

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
