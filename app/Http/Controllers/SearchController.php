<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const ALLOWED_STATUSES = ['ongoing', 'completed', 'paused'];

    private const ALLOWED_SORTS = ['newest', 'updated', 'most_viewed', 'top_rated', 'relevance'];

    private const ALLOWED_PERIODS = ['week', 'month'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:ongoing,completed,paused'],
            'sort' => ['nullable', 'in:newest,updated,most_viewed,top_rated,relevance'],
            'period' => ['nullable', 'in:week,month'],
        ]);

        $keyword = trim($filters['q'] ?? '');
        $categorySlug = $filters['category'] ?? '';
        $status = $filters['status'] ?? '';
        $sort = $filters['sort'] ?? 'newest';
        $period = $filters['period'] ?? '';

        $query = Story::published()->with('categories');

        if ($keyword !== '') {
            // Treat SQL LIKE metacharacters as literal user input. "!" is used
            // as the ESCAPE character to avoid driver-specific backslash rules.
            $escapedKeyword = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
            $term = '%'.$escapedKeyword.'%';

            $query->where(function ($search) use ($term) {
                $search->whereRaw("title LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("author_name LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('categories', fn ($categories) => $categories->whereRaw("name LIKE ? ESCAPE '!'", [$term]))
                    ->orWhereHas('tags', fn ($tags) => $tags->whereRaw("name LIKE ? ESCAPE '!'", [$term]));
            });

            if ($sort === 'relevance') {
                $prefix = $escapedKeyword.'%';

                // Keep substring matching semantics, but rank stronger matches first.
                // Category/tag matches remain eligible and follow the story text matches.
                $query->orderByRaw(
                    "CASE
                        WHEN title = ? THEN 0
                        WHEN title LIKE ? ESCAPE '!' THEN 1
                        WHEN title LIKE ? ESCAPE '!' THEN 2
                        WHEN author_name = ? THEN 3
                        WHEN author_name LIKE ? ESCAPE '!' THEN 4
                        WHEN description LIKE ? ESCAPE '!' THEN 5
                        ELSE 6
                    END ASC",
                    [$keyword, $prefix, $term, $keyword, $prefix, $term]
                );
            }
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

        if ($sort === 'top_rated') {
            // Avoid presenting a tiny number of ratings as a reliable ranking.
            $query->where('rating_count', '>=', 5);
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
