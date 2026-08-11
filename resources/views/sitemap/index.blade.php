<?php echo '<?xml version="1.0" encoding="UTF-8"?' . '>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>{{ route('sitemap.static') }}</loc>
    </sitemap>

    <sitemap>
        <loc>{{ route('sitemap.categories') }}</loc>
        @if ($lastCategoryUpdate)
            <lastmod>{{ \Illuminate\Support\Carbon::parse($lastCategoryUpdate)->toAtomString() }}</lastmod>
        @endif
    </sitemap>

    @for ($page = 1; $page <= $totalPages; $page++)
        <sitemap>
            <loc>{{ route('sitemap.stories', ['page' => $page]) }}</loc>
            @if ($lastStoryUpdate)
                <lastmod>{{ \Illuminate\Support\Carbon::parse($lastStoryUpdate)->toAtomString() }}</lastmod>
            @endif
        </sitemap>
    @endfor
</sitemapindex>