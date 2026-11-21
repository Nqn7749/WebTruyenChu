<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunCrawlerSource;
use App\Models\CrawlerRun;
use App\Models\CrawlerSource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CrawlerSourceController extends Controller
{
    private const SELECTORS = [
        'story_link', 'story_title', 'story_author', 'story_description',
        'chapter_link', 'chapter_title', 'chapter_content',
    ];

    public function index()
    {
        return view('admin.crawler.index', [
            'sources' => CrawlerSource::with('runs')->withCount('items')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $source = CrawlerSource::create($this->validated($request));
        return redirect()->route('admin.crawler.index')->with('success', 'Đã thêm nguồn crawler.');
    }

    public function update(Request $request, CrawlerSource $crawlerSource)
    {
        $crawlerSource->update($this->validated($request));
        return redirect()->route('admin.crawler.index')->with('success', 'Đã cập nhật nguồn crawler.');
    }

    public function destroy(CrawlerSource $crawlerSource)
    {
        $crawlerSource->delete();
        return back()->with('success', 'Đã xóa nguồn crawler và lịch sử liên quan.');
    }

    public function run(Request $request, CrawlerSource $crawlerSource)
    {
        abort_unless($crawlerSource->enabled && $crawlerSource->permission_confirmed, 422, 'Nguồn phải được bật và xác nhận quyền sử dụng.');

        $recentRun = $crawlerSource->runs()->whereIn('status', ['queued', 'running'])->exists();
        if ($recentRun) {
            return back()->withErrors(['crawler' => 'Nguồn này đang có một lượt thu thập chạy.']);
        }

        $run = CrawlerRun::create([
            'crawler_source_id' => $crawlerSource->id,
            'started_by' => $request->user()->id,
            'status' => 'queued',
        ]);

        RunCrawlerSource::dispatch($run->id);

        return back()->with('success', 'Đã đưa crawler vào hàng đợi. Nội dung mới sẽ ở trạng thái nháp.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:2048'],
            'listing_url' => ['required', 'url', 'max:2048'],
            'permission_confirmed' => ['accepted'],
            'enabled' => ['nullable', 'boolean'],
            'selectors' => ['required', 'array'],
            'selectors.story_link' => ['required', 'string', 'max:500'],
            'selectors.story_title' => ['required', 'string', 'max:500'],
            'selectors.story_author' => ['nullable', 'string', 'max:500'],
            'selectors.story_description' => ['nullable', 'string', 'max:500'],
            'selectors.chapter_link' => ['required', 'string', 'max:500'],
            'selectors.chapter_title' => ['required', 'string', 'max:500'],
            'selectors.chapter_content' => ['required', 'string', 'max:500'],
        ]);

        foreach (self::SELECTORS as $key) {
            $expression = trim($data['selectors'][$key] ?? '');
            if ($expression !== '' && ! str_starts_with($expression, '/')) {
                throw ValidationException::withMessages([
                    'selectors.'.$key => 'XPath phải bắt đầu bằng dấu /.',
                ]);
            }
        }

        $base = parse_url($data['base_url']);
        $listing = parse_url($data['listing_url']);
        if (($base['scheme'] ?? '') !== 'https'
            || strtolower($base['host'] ?? '') !== strtolower($listing['host'] ?? '')
            || isset($base['user']) || isset($base['pass'])
            || isset($listing['user']) || isset($listing['pass'])) {
            throw ValidationException::withMessages([
                'base_url' => 'Base URL và URL danh sách phải cùng một host HTTPS, không chứa thông tin đăng nhập.',
            ]);
        }

        $data['permission_confirmed'] = true;
        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        return $data;
    }
}
