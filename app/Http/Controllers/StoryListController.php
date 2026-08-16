<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;

class StoryListController extends Controller
{
    private const CHAPTER_RANGES = [
        'under_300' => [null, 300],
        '300_600'   => [300, 600],
        '600_1000'  => [600, 1000],
        'over_1000' => [1000, null],
    ];

    private const ALLOWED_STATUSES = ['ongoing', 'completed', 'paused'];

    private const ALLOWED_SORTS = [
        'latest_chapter',
        'newest_story',
        'most_viewed',
        'top_rated',
    ];

    /**
     * Số lượt đánh giá tối thiểu để được xét vào "Đánh giá cao nhất",
     * tránh truyện chỉ có 1-2 lượt rating 5 sao chiếm top ảo.
     */
    private const MIN_RATING_COUNT_FOR_TOP_RATED = 5;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $categoryIds = collect((array) $request->query('categories', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $chapterRange = $request->query('chapters');
        if (! array_key_exists($chapterRange, self::CHAPTER_RANGES)) {
            $chapterRange = null;
        }

        $sort = $request->query('sort', 'latest_chapter');
        if (! in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'latest_chapter';
        }

        $query = Story::published()->with('categories');

        if ($status && in_array($status, self::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if (! empty($categoryIds)) {
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        if ($chapterRange) {
            [$min, $max] = self::CHAPTER_RANGES[$chapterRange];

            if ($min !== null) {
                $query->where('chapter_count', '>=', $min);
            }

            if ($max !== null) {
                $query->where('chapter_count', '<', $max);
            }
        }

        match ($sort) {
            'newest_story' => $query->orderByDesc('created_at'),
            'most_viewed'  => $query->orderByDesc('views'),
            'top_rated'    => $query->where('rating_count', '>=', self::MIN_RATING_COUNT_FOR_TOP_RATED)
                                     ->orderByDesc('average_rating')
                                     ->orderByDesc('rating_count'),
            default        => $query->orderByDesc('last_chapter_at'),
        };

        $stories = $query->paginate(24)->withQueryString();

        $categories = Category::orderBy('name')->get(['id', 'parent_id', 'name']);

        return view('stories.index', [
            'stories'      => $stories,
            'status'       => $status,
            'categoryIds'  => $categoryIds,
            'chapterRange' => $chapterRange,
            'sort'         => $sort,
            'categories'   => $categories,
        ]);
    }
}