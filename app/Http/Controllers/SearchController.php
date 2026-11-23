<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    private const ALLOWED_STATUSES = ['ongoing', 'completed', 'paused'];

    private const ALLOWED_SORTS = ['newest', 'updated', 'most_viewed', 'top_rated', 'relevance'];

    private const ALLOWED_PERIODS = ['week', 'month'];

    public function index(Request $request)
    {
        // Query param q dạng array (?q[]=x) làm validate rớt rồi
        // redirect 302 về trang trước, refresh lại sẽ lặp (V1): chuẩn
        // hóa về null ngay từ đầu, coi array như không có từ khóa.
        if (is_array($request->query('q'))) {
            $request->merge(['q' => null]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:ongoing,completed,paused'],
            'sort' => ['nullable', 'in:newest,updated,most_viewed,top_rated,relevance'],
            'period' => ['nullable', 'in:week,month'],
        ]);

        $keyword = trim($filters['q'] ?? '');
        $categorySlug = $filters['category'] ?? '';
        $tagSlug = $filters['tag'] ?? '';
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

        if ($tagSlug !== '') {
            $query->whereHas('tags', fn ($tags) => $tags->where('slug', $tagSlug));
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
        // Normalize page theo đúng luật của Paginator::currentPageResolver
        // (xem AppServiceProvider) rồi mới đưa vào key cache, để
        // ?page=abc, ?page=1, ?page=1001 không tạo nhiều entry cache
        // trùng nhau cho cùng một kết quả (C7).
        $rawPage = $request->query('page', 1);
        $page = is_numeric($rawPage) ? min(1000, max(1, (int) $rawPage)) : 1;

        $cacheKey = 'search:'.md5(json_encode([
            'q' => $keyword,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'status' => $status,
            'sort' => $sort,
            'period' => $period,
            'page' => $page,
        ]));

        // Chỉ cache dữ liệu kết quả, KHÔNG cache paginator: paginator giữ
        // query string lúc tạo (withQueryString) nên cache nó sẽ đóng
        // băng query string của request đầu tiên vào link phân trang của
        // mọi request sau (L4). Rebuild paginator mới mỗi request để
        // link luôn mang query string hiện tại.
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            // Chống stampede: chỉ một request chạy truy vấn đắt, các
            // request khác chờ lock rồi đọc lại cache; nếu vẫn trống
            // thì tự tính mà không ghi cache.
            $lock = Cache::lock($cacheKey.':lock', 10);

            if ($lock->acquire()) {
                try {
                    $cached = $this->cachedSearchResults($query);
                    Cache::put($cacheKey, $cached, now()->addSeconds(60));
                } finally {
                    $lock->release();
                }
            } else {
                $cached = Cache::get($cacheKey) ?? $this->cachedSearchResults($query);
            }
        }

        $stories = new LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            $cached['per_page'],
            $cached['current_page'],
            ['path' => $request->url(), 'pageName' => 'page']
        );
        $stories->withQueryString();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        $activeTag = $tagSlug !== '' ? Tag::query()->where('slug', $tagSlug)->first(['id', 'name', 'slug']) : null;

        return view('search.index', compact(
            'stories',
            'keyword',
            'categories',
            'categorySlug',
            'tagSlug',
            'activeTag',
            'status',
            'sort',
            'period'
        ));
    }

    /**
     * Chạy truy vấn tìm kiếm và trả về dữ liệu thuần để cache (items +
     * số liệu phân trang), không kèm paginator — paginator được rebuild
     * mới ở mỗi request (xem index()).
     */
    private function cachedSearchResults(Builder $query): array
    {
        $paginator = $query->orderByDesc('id')->paginate(15);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
        ];
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
     * tất cả các từ đều xuất hiện (SE-M1), và * vào cuối mỗi từ để khớp
     * tiền tố: gõ dở "truy" vẫn tìm được truyện "Truyện kiếm hiệp",
     * vì đó là cách người dùng tìm kiếm phổ biến nhất.
     */
    private function toBooleanKeyword(string $keyword): string
    {
        // InnoDB treats "_" as part of a word ("chap_ter" is one token), so keep it;
        // splitting on it would require tokens that do not exist in the index.
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}_\s]/u', ' ', $keyword);
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', $cleaned));

        if ($cleaned === '') {
            return '';
        }

        return '+'.implode('* +', explode(' ', $cleaned)).'*';
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
