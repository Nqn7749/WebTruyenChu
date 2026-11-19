<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Số truyện tối đa mỗi file sitemap con.
     */
    public const STORIES_PER_PAGE = 5000;

    /**
     * Sitemap Index — liệt kê tất cả sitemap con.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', now()->addHour(), function () {
            $totalStories = Story::published()->count();
            $totalPages = (int) ceil($totalStories / self::STORIES_PER_PAGE);
            $publishedChapters = Chapter::query()
                ->where('status', true)
                ->whereHas('story', fn ($query) => $query->published());
            $totalChapterPages = (int) ceil((clone $publishedChapters)->count() / self::STORIES_PER_PAGE);

            $lastStoryUpdate = Story::published()->max('updated_at');
            $lastCategoryUpdate = Category::whereHas('stories', fn ($query) => $query->published())->max('updated_at');
            $lastChapterUpdate = (clone $publishedChapters)->max('updated_at');

            return view('sitemap.index', [
                'totalPages'         => $totalPages,
                'totalChapterPages'  => $totalChapterPages,
                'lastStoryUpdate'    => $lastStoryUpdate,
                'lastCategoryUpdate' => $lastCategoryUpdate,
                'lastChapterUpdate'  => $lastChapterUpdate,
            ])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Sitemap con: danh sách truyện, chia trang.
     */
    public function stories(int $page): Response
    {
        $xml = Cache::remember("sitemap:stories:{$page}", now()->addHour(), function () use ($page) {
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


    /**
     * Sitemap con: danh sách chương đã xuất bản.
     */
    public function chapters(int $page): Response
    {
        $xml = Cache::remember("sitemap:chapters:{$page}", now()->addHour(), function () use ($page) {
            $chapters = Chapter::query()
                ->where('status', true)
                ->whereHas('story', fn ($query) => $query->published())
                ->with('story:id,slug')
                ->orderBy('id')
                ->skip(($page - 1) * self::STORIES_PER_PAGE)
                ->take(self::STORIES_PER_PAGE)
                ->get(['id', 'story_id', 'chapter_number', 'updated_at']);

            abort_if($chapters->isEmpty(), 404);

            return view('sitemap.chapters', compact('chapters'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Sitemap con: danh sách thể loại.
     */
    public function categories(): Response
    {
        $xml = Cache::remember('sitemap:categories', now()->addHour(), function () {
            $categories = Category::query()
                ->whereHas('stories', fn ($query) => $query->published())
                ->orderBy('id')
                ->get(['slug', 'updated_at']);

            return view('sitemap.categories', compact('categories'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Sitemap con: các trang tĩnh (home, tìm kiếm...).
     */
    public function staticPages(): Response
    {
        $xml = Cache::remember('sitemap:static', now()->addHour(), function () {
            return view('sitemap.static')->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}