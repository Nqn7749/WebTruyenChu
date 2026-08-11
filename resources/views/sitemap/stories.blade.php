<?php echo '<?xml version="1.0" encoding="UTF-8"?' . '>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($stories as $story)
        <url>
            <loc>{{ route('stories.show', $story) }}</loc>
            <lastmod>{{ $story->updated_at->toAtomString() }}</lastmod>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>