<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryUrlImportService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportStoryChapter implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120];

    public function __construct(
        public int $storyId,
        public string $title,
        public string $url,
        public string $sourceHash,
        public int $chapterNumber,
    ) {}

    public function handle(StoryUrlImportService $importer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $story = Story::query()->findOrFail($this->storyId);

        if (Chapter::withTrashed()->where('source_hash', $this->sourceHash)->exists()) {
            return;
        }

        // Network I/O stays outside the database transaction. Each chapter
        // is an independent job, so a slow or unavailable source cannot hold
        // locks or block the remaining chapters in the batch.
        try {
            $content = $importer->fetchChapter($this->url);
        } catch (Throwable $exception) {
            // Name the chapter in the failure so the admin progress page can
            // show which chapter failed and why.
            throw new RuntimeException($this->title.' ('.$this->url.'): '.$exception->getMessage(), 0, $exception);
        }

        DB::transaction(function () use ($story, $content): void {
            // Re-check after the HTTP request to make retries and duplicate
            // deliveries safe if another worker imported this URL meanwhile.
            if (Chapter::withTrashed()->where('source_hash', $this->sourceHash)->exists()) {
                return;
            }

            $chapter = new Chapter;
            $chapter->forceFill([
                'story_id' => $story->id,
                'chapter_number' => $this->chapterNumber,
                'title' => $this->title,
                'slug' => 'chuong-'.$this->chapterNumber.'-'.Str::lower(Str::random(6)),
                'content' => $content,
                'status' => false,
                'source_url' => $this->url,
                'source_hash' => $this->sourceHash,
            ]);
            $chapter->save();
        });
    }
}
