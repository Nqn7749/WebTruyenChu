<?php echo '<?xml version="1.0" encoding="UTF-8"?' . '>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($chapters as $chapter)
        <url>
            <loc>{{ route('chapters.show', [$chapter->story, $chapter]) }}</loc>
            <lastmod>{{ $chapter->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
