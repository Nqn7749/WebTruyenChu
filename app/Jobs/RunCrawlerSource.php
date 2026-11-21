<?php

namespace App\Jobs;

use App\Models\CrawlerItem;
use App\Models\CrawlerRun;
use App\Models\CrawlerSource;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunCrawlerSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = CrawlerRun::with('source')->findOrFail($this->runId);
        $source = $run->source;

        if (! $source->enabled || ! $source->permission_confirmed) {
            $run->update(['status' => 'cancelled', 'message' => 'Nguồn chưa bật hoặc chưa xác nhận quyền sử dụng.', 'finished_at' => now()]);
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);
        $counts = ['stories_found' => 0, 'stories_imported' => 0, 'chapters_imported' => 0];

        try {
            $listing = $this->getDocument($source, $source->listing_url);
            $links = $this->nodes($listing, $source->selectors['story_link'] ?? '');
            $counts['stories_found'] = min(count($links), 20);

            foreach (array_slice($links, 0, 20) as $linkNode) {
                $externalUrl = $this->absoluteUrl($source, $linkNode->getAttribute('href'));
                $storyPage = $this->getDocument($source, $externalUrl);
                $title = $this->value($storyPage, $source->selectors['story_title'] ?? '');
                if ($title === '') {
                    continue;
                }

                $item = CrawlerItem::where('crawler_source_id', $source->id)
                    ->where('external_key', $externalUrl)->first();

                if ($item) {
                    $story = $item->story;
                } else {
                    $slug = (Str::slug($title) ?: 'truyen').'-'.Str::lower(Str::random(7));
                    $story = new Story;
                    $story->forceFill([
                        'user_id' => $run->started_by,
                        'title' => Str::limit($title, 255, ''),
                        'slug' => $slug,
                        'author_name' => $this->value($storyPage, $source->selectors['story_author'] ?? '') ?: null,
                        'description' => $this->value($storyPage, $source->selectors['story_description'] ?? '') ?: null,
                        'status' => 'ongoing',
                        'status_publish' => false,
                    ])->save();

                    CrawlerItem::create([
                        'crawler_source_id' => $source->id,
                        'external_key' => $externalUrl,
                        'story_id' => $story->id,
                    ]);
                    $counts['stories_imported']++;
                }

                $chapterLinks = $this->nodes($storyPage, $source->selectors['chapter_link'] ?? '');
                $nextNumber = (int) $story->chapters()->withTrashed()->max('chapter_number') + 1;

                foreach (array_slice($chapterLinks, 0, 50) as $chapterLink) {
                    $chapterUrl = strtok($this->absoluteUrl($source, $chapterLink->getAttribute('href')), '#');
                    if (DB::table('crawler_chapter_items')->where('crawler_source_id', $source->id)->where('external_key', $chapterUrl)->exists()) {
                        continue;
                    }

                    $chapterPage = $this->getDocument($source, $chapterUrl);
                    $chapterTitle = $this->value($chapterPage, $source->selectors['chapter_title'] ?? '') ?: 'Chương '.$nextNumber;
                    $content = $this->value($chapterPage, $source->selectors['chapter_content'] ?? '');
                    if ($content === '') {
                        continue;
                    }

                    $chapter = $story->chapters()->create([
                        'chapter_number' => $nextNumber,
                        'title' => Str::limit($chapterTitle, 255, ''),
                        'slug' => 'chuong-'.$nextNumber.'-'.Str::lower(Str::random(5)),
                        'content' => $content,
                        'status' => false,
                    ]);

                    DB::table('crawler_chapter_items')->insert([
                        'crawler_source_id' => $source->id,
                        'story_id' => $story->id,
                        'external_key' => $chapterUrl,
                        'chapter_id' => $chapter->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $nextNumber++;
                    $counts['chapters_imported']++;
                }
            }

            $source->update(['last_crawled_at' => now()]);
            $run->update($counts + ['status' => 'completed', 'message' => 'Đã thu thập xong. Nội dung được lưu ở trạng thái nháp để quản trị viên kiểm duyệt.', 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $run->update($counts + ['status' => 'failed', 'message' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
            throw $exception;
        }
    }

    private function getDocument(CrawlerSource $source, string $url): \DOMXPath
    {
        $this->assertAllowedUrl($source, $url);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders(['User-Agent' => 'WebTruyenChuBot/1.0 (+crawler; respect source permission)'])
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Nguồn trả về HTTP '.$response->status().'.');
        }

        $html = $response->body();
        if (strlen($html) > 2_000_000) {
            throw new RuntimeException('Trang nguồn vượt quá giới hạn 2 MB.');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Không thể phân tích HTML từ nguồn.');
        }

        return new \DOMXPath($dom);
    }

    private function nodes(\DOMXPath $document, string $expression): array
    {
        if ($expression === '' || ! str_starts_with(trim($expression), '/')) {
            throw new RuntimeException('XPath cấu hình không hợp lệ.');
        }

        $nodes = $document->query($expression);
        if ($nodes === false) {
            throw new RuntimeException('Không thể thực thi XPath cấu hình.');
        }

        $result = [];
        foreach ($nodes as $node) {
            $result[] = $node;
        }

        return $result;
    }

    private function value(\DOMXPath $document, string $expression): string
    {
        if (trim($expression) === '') {
            return '';
        }

        $nodes = $this->nodes($document, $expression);
        if (! isset($nodes[0])) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $nodes[0]->textContent) ?? '');
    }

    private function absoluteUrl(CrawlerSource $source, string $href): string
    {
        if ($href === '' || str_starts_with($href, '//')) {
            throw new RuntimeException('Liên kết nguồn không hợp lệ.');
        }

        if (str_starts_with($href, 'https://')) {
            $url = $href;
        } elseif (str_starts_with($href, '/')) {
            $url = rtrim($source->base_url, '/').$href;
        } else {
            $url = rtrim($source->base_url, '/').'/'.$href;
        }

        $this->assertAllowedUrl($source, $url);
        return $url;
    }

    private function assertAllowedUrl(CrawlerSource $source, string $url): void
    {
        $base = parse_url($source->base_url);
        $target = parse_url($url);
        if (! $base || ! $target || ($target['scheme'] ?? '') !== 'https'
            || strtolower($target['host'] ?? '') !== strtolower($base['host'] ?? '')
            || isset($target['user']) || isset($target['pass'])
            || (isset($target['port']) && $target['port'] !== 443)) {
            throw new RuntimeException('Crawler chỉ được truy cập HTTPS cùng host đã cấu hình.');
        }

        $host = $target['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Không phân giải được máy chủ nguồn.');
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Địa chỉ máy chủ nguồn không được phép.');
        }
    }
}
