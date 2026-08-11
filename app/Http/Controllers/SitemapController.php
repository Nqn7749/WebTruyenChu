<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Số truyện tối đa mỗi file sitemap con.
     */
    private const STORIES_PER_PAGE = 5000;

    /**
     * Sitemap Index — liệt kê tất cả sitemap con.
     */
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', now()->addHour(), function () {
            $totalStories = Story::published()->count();
            $totalPages = max(1, (int) ceil($totalStories / self::STORIES_PER_PAGE));

            $lastStoryUpdate = Story::published()->max('updated_at');
            $lastCategoryUpdate = Category::max('updated_at');

            return view('sitemap.index', [
                'totalPages'         => $totalPages,
                'lastStoryUpdate'    => $lastStoryUpdate,
                'lastCategoryUpdate' => $lastCategoryUpdate,
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
     * Sitemap con: danh sách thể loại.
     */
    public function categories(): Response
    {
        $xml = Cache::remember('sitemap:categories', now()->addHour(), function () {
            $categories = Category::orderBy('id')->get(['slug', 'updated_at']);

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