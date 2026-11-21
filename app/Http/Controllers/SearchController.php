<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Kết quả tìm kiếm giống nhau cho mọi người (chỉ truyện công khai)
        // nên cache ngắn 60 giây theo đúng bộ lọc + trang: truy vấn tìm
        // kiếm đắt (FULLTEXT OR LIKE + whereHas) mà bot hoặc người dùng
        // bấm lại liên tục sẽ quét bảng nhiều lần (P-M1). Truyện mới
        // đăng có thể chậm xuất hiện trong tìm kiếm tối đa 60 giây.
        $cacheKey = 'search:'.md5(json_encode([
            'q' => $keyword,
            'category' => $categorySlug,
            'status' => $status,
            'sort' => $sort,
            'period' => $period,
            'page' => $request->query('page', 1),
        ]));

        $stories = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($query) {
            return $query
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
        });

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
     * và từ khóa có ít nhất một từ đủ dài để index nhận (M7) và không
     * phải stopword: stopword không có trong index nên MATCH với toàn
     * từ stopword trả về rỗng (SE-L1).
     */
    private function useFullText(string $keyword): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        // MySQL bỏ qua từ ngắn hơn ft_min_word_len (mặc định 4) trong
        // index FULLTEXT: từ khóa chỉ toàn từ ngắn thì MATCH trả về rỗng.
        $words = preg_split('/\s+/u', mb_strtolower($keyword), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $clean = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $word);

            if (preg_match('/\p{L}{4,}/u', $clean) === 1 && ! in_array($clean, self::FULLTEXT_STOPWORDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loại ký tự điều khiển của BOOLEAN MODE (+ - * " ( ) < > ~) để input
     * của user không đổi nghĩa truy vấn, rồi gộp khoảng trắng.
     *
     * Boolean mode mặc định là OR giữa các từ: "kiếm hiệp" trả cả truyện
     * chỉ có "kiếm" hoặc chỉ có "hiệp". Thêm + vào mỗi từ để bắt buộc
     * tất cả các từ đều xuất hiện (SE-M1).
     */
    private function toBooleanKeyword(string $keyword): string
    {
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', $cleaned));

        if ($cleaned === '') {
            return '';
        }

        return '+'.implode(' +', explode(' ', $cleaned));
    }

    /**
     * Tập con các stopword mặc định của InnoDB (xem MySQL manual,
     * "InnoDB Full-Text Stopwords") mà người dùng thực tế có thể gõ
     * khi tìm truyện: chỉ giữ từ có từ 4 ký tự trở lên vì từ ngắn hơn
     * đã bị loại bởi kiểm tra độ dài ở useFullText(). Triển khai
     * MySQL dùng ft_stopword_file riêng thì cập nhật lại danh sách
     * này theo file đó.
     */
    private const FULLTEXT_STOPWORDS = [
        'about', 'above', 'after', 'again', 'against', 'almost', 'alone',
        'along', 'already', 'also', 'although', 'always', 'among',
        'amongst', 'another', 'anyone', 'anything', 'anyway', 'anywhere',
        'apart', 'appear', 'appreciate', 'appropriate', 'around', 'aside',
        'asking', 'associated', 'available', 'away', 'awfully', 'became',
        'because', 'become', 'becomes', 'becoming', 'been', 'before',
        'beforehand', 'behind', 'being', 'believe', 'below', 'beside',
        'besides', 'best', 'better', 'between', 'beyond', 'both', 'brief',
        'came', 'cannot', 'cause', 'causes', 'certain', 'certainly',
        'changes', 'clearly', 'come', 'comes', 'concerning',
        'consequently', 'consider', 'considering', 'contain', 'containing',
        'contains', 'corresponding', 'could', 'course', 'currently',
        'definitely', 'described', 'despite', 'different', 'doing',
        'done', 'down', 'downwards', 'during', 'each', 'either', 'else',
        'elsewhere', 'enough', 'entirely', 'especially', 'even', 'ever',
        'every', 'everybody', 'everyone', 'everything', 'everywhere',
        'exactly', 'example', 'except', 'fifth', 'first', 'five',
        'followed', 'following', 'follows', 'former', 'formerly', 'forth',
        'four', 'from', 'further', 'furthermore', 'gets', 'getting',
        'given', 'gives', 'goes', 'going', 'gone', 'gotten', 'greetings',
        'happens', 'hardly', 'having', 'hence', 'here', 'hereby', 'herein',
        'hereupon', 'hers', 'herself', 'himself', 'hither', 'hopefully',
        'however', 'ignored', 'immediate', 'indeed', 'indicate',
        'indicated', 'indicates', 'inner', 'insofar', 'instead', 'into',
        'inward', 'itself', 'keep', 'keeps', 'kept', 'know', 'known',
        'knows', 'last', 'lately', 'later', 'latter', 'latterly', 'least',
        'less', 'lest', 'like', 'liked', 'likely', 'little', 'look',
        'looking', 'looks', 'mainly', 'many', 'maybe', 'meanwhile',
        'merely', 'might', 'more', 'moreover', 'most', 'mostly', 'much',
        'must', 'myself', 'namely', 'near', 'nearly', 'necessary', 'need',
        'needs', 'neither', 'never', 'nevertheless', 'next', 'nine',
        'nobody', 'none', 'noone', 'normal', 'normally', 'nothing',
        'novel', 'nowhere', 'obviously', 'often', 'once', 'ones', 'only',
        'onto', 'other', 'others', 'otherwise', 'ought', 'ours',
        'ourselves', 'out', 'outside', 'over', 'overall', 'own', 'part',
        'particular', 'particularly', 'perhaps', 'placed', 'please',
        'plus', 'possible', 'presumably', 'probably', 'provides', 'quite',
        'rather', 'really', 'reasonably', 'regarding', 'regardless',
        'regards', 'relatively', 'respectively', 'right', 'said', 'same',
        'saw', 'say', 'saying', 'says', 'second', 'secondly', 'see',
        'seeing', 'seem', 'seemed', 'seeming', 'seems', 'seen', 'self',
        'selves', 'sensible', 'sent', 'serious', 'seriously', 'seven',
        'several', 'shall', 'should', 'since', 'some', 'somebody',
        'somehow', 'someone', 'something', 'sometime', 'sometimes',
        'somewhat', 'somewhere', 'soon', 'sorry', 'specified', 'specify',
        'specifying', 'still', 'such', 'sure', 'take', 'taken', 'tell',
        'tends', 'than', 'thank', 'thanks', 'that', 'their', 'theirs',
        'them', 'themselves', 'then', 'thence', 'there', 'thereafter',
        'thereby', 'therefore', 'therein', 'thereupon', 'these', 'they',
        'think', 'third', 'thorough', 'thoroughly', 'those', 'though',
        'three', 'through', 'throughout', 'thus', 'together', 'took',
        'toward', 'towards', 'tried', 'tries', 'truly', 'try', 'trying',
        'twice', 'under', 'unfortunately', 'unless', 'unlikely', 'until',
        'unto', 'upon', 'used', 'useful', 'uses', 'using', 'usually',
        'value', 'various', 'very', 'want', 'wants', 'was', 'way',
        'welcome', 'well', 'went', 'were', 'what', 'whatever', 'when',
        'whence', 'whenever', 'where', 'whereas', 'whereby', 'wherein',
        'whereupon', 'wherever', 'whether', 'which', 'while', 'whither',
        'who', 'whoever', 'whole', 'whom', 'whose', 'why', 'will',
        'willing', 'wish', 'with', 'within', 'without', 'wonder', 'would',
        'yet', 'your', 'yours', 'yourself', 'yourselves', 'zero',
    ];
}
