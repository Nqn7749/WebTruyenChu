<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\SitemapCacheService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public const STORIES_PER_PAGE = 5000;

    public function __construct(private readonly SitemapCacheService $sitemapCache) {}

    public function index(): Response
    {
        $xml = Cache::remember($this->sitemapCache->key('index'), now()->addHour(), function () {
            $totalStories = Story::published()->count();
            $totalStoryPages = (int) ceil($totalStories / self::STORIES_PER_PAGE);
            $totalChapters = Chapter::query()
                ->where('status', true)
                ->whereHas('story', fn ($query) => $query->published())
                ->count();
            $totalChapterPages = (int) ceil($totalChapters / self::STORIES_PER_PAGE);

            return view('sitemap.index', [
                'totalStoryPages' => $totalStoryPages,
                'totalChapterPages' => $totalChapterPages,
                'lastStoryUpdate' => Story::published()->max('updated_at'),
                'lastChapterUpdate' => Chapter::query()
                    ->where('status', true)
                    ->whereHas('story', fn ($query) => $query->published())
                    ->max('published_at'),
                'lastCategoryUpdate' => Category::max('updated_at'),
            ])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function stories(int $page): Response
    {
        abort_if($page < 1, 404);

        $xml = Cache::remember($this->sitemapCache->key("stories:{$page}"), now()->addHour(), function () use ($page) {
            $stories = Story::published()
                ->orderBy('id')
                ->skip(($page - 1) * self::STORIES_PER_PAGE)
                ->take(self::STORIES_PER_PAGE)
                ->get(['slug', 'updated_at']);

            abort_if($stories->isEmpty(), 404);

            return view('sitemap.stories', compact('stories'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function chapters(int $page): Response
    {
        abort_if($page < 1, 404);

        $xml = Cache::remember($this->sitemapCache->key("chapters:{$page}"), now()->addHour(), function () use ($page) {
            $chapters = Chapter::query()
                ->where('status', true)
                ->whereHas('story', fn ($query) => $query->published())
                ->with('story:id,slug')
                ->orderBy('id')
                ->skip(($page - 1) * self::STORIES_PER_PAGE)
                ->take(self::STORIES_PER_PAGE)
                ->get(['id', 'story_id', 'slug', 'published_at', 'created_at']);

            abort_if($chapters->isEmpty(), 404);

            return view('sitemap.chapters', compact('chapters'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function categories(): Response
    {
        $xml = Cache::remember($this->sitemapCache->key('categories'), now()->addHour(), function () {
            $categories = Category::orderBy('id')->get(['slug', 'updated_at']);

            return view('sitemap.categories', compact('categories'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function staticPages(): Response
    {
        $xml = Cache::remember($this->sitemapCache->key('static'), now()->addHour(), function () {
            return view('sitemap.static')->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
