<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const ALLOWED_STATUSES = ['ongoing', 'completed', 'paused'];

    private const ALLOWED_SORTS = ['newest', 'updated', 'most_viewed', 'top_rated'];

    private const ALLOWED_PERIODS = ['week', 'month'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:ongoing,completed,paused'],
            'sort' => ['nullable', 'in:newest,updated,most_viewed,top_rated'],
            'period' => ['nullable', 'in:week,month'],
        ]);

        $keyword = trim($filters['q'] ?? '');
        $categorySlug = $filters['category'] ?? '';
        $status = $filters['status'] ?? '';
        $sort = $filters['sort'] ?? 'newest';
        $period = $filters['period'] ?? '';

        $query = Story::published()->with('categories');

        if ($keyword !== '') {
            $term = '%'.$keyword.'%';

            $query->where(function ($search) use ($term) {
                $search->where('title', 'like', $term)
                    ->orWhere('author_name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('categories', fn ($categories) => $categories->where('name', 'like', $term))
                    ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', $term));
            });
        }

        if ($categorySlug !== '') {
            $query->whereHas('categories', fn ($categories) => $categories->where('slug', $categorySlug));
        }

        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($period !== '' && in_array($period, self::ALLOWED_PERIODS, true)) {
            $query->where('last_chapter_at', '>=', now()->subDays($period === 'week' ? 7 : 30));
        }

        match ($sort) {
            'updated' => $query->orderByDesc('last_chapter_at'),
            'most_viewed' => $query->orderByDesc('views'),
            'top_rated' => $query->orderByDesc('average_rating')->orderByDesc('rating_count'),
            default => $query->orderByDesc('created_at'),
        };

        $stories = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return view('search.index', compact(
            'stories',
            'keyword',
            'categories',
            'categorySlug',
            'status',
            'sort',
            'period'
        ));
    }
}
