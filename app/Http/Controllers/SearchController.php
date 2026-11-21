<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            if ($this->useFullText($keyword)) {
                // Index FULLTEXT (title, description) đã có sẵn từ migration
                // tạo bảng stories: dùng MATCH cho phần text, LIKE cho
                // author_name và thể loại/tag (không nằm trong index) (M7).
                $booleanKeyword = $this->toBooleanKeyword($keyword);

                $query->where(function ($search) use ($booleanKeyword, $term) {
                    $search->whereFullText(['title', 'description'], $booleanKeyword, ['mode' => 'boolean'])
                        ->orWhereRaw("author_name LIKE ? ESCAPE '!'", [$term])
                        ->orWhereHas('categories', fn ($categories) => $categories->whereRaw("name LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('tags', fn ($tags) => $tags->whereRaw("name LIKE ? ESCAPE '!'", [$term]));
                });
            } else {
                $query->where(function ($search) use ($term) {
                    $search->whereRaw("title LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("author_name LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("description LIKE ? ESCAPE '!'", [$term])
                        ->orWhereHas('categories', fn ($categories) => $categories->whereRaw("name LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('tags', fn ($tags) => $tags->whereRaw("name LIKE ? ESCAPE '!'", [$term]));
                });
            }

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

    /**
     * Chỉ dùng MATCH ... AGAINST khi DB có index FULLTEXT (MySQL/MariaDB)
     * và từ khóa có ít nhất một từ đủ dài để index nhận (M7).
     */
    private function useFullText(string $keyword): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        // MySQL bỏ qua từ ngắn hơn ft_min_word_len (mặc định 4) trong
        // index FULLTEXT: từ khóa chỉ toàn từ ngắn thì MATCH trả về rỗng.
        return preg_match('/\p{L}{4,}/u', $keyword) === 1;
    }

    /**
     * Loại ký tự điều khiển của BOOLEAN MODE (+ - * " ( ) < > ~) để input
     * của user không đổi nghĩa truy vấn, rồi gộp khoảng trắng.
     */
    private function toBooleanKeyword(string $keyword): string
    {
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);

        return trim((string) preg_replace('/\s+/u', ' ', $cleaned));
    }
}
