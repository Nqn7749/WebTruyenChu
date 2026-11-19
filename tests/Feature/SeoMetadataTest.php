<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_exposes_indexable_seo_metadata(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('home').'"', false)
            ->assertSee('property="og:locale" content="vi_VN"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_search_results_are_not_indexed(): void
    {
        $response = $this->get(route('search', ['q' => 'kiem thu']));

        $response->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false)
            ->assertSee('rel="canonical" href="'.route('search').'"', false);
    }

    public function test_story_page_contains_story_metadata_and_book_structured_data(): void
    {
        $story = Story::factory()->create([
            'status_publish' => true,
            'meta_title' => 'Tựa đề SEO',
            'meta_description' => 'Mô tả SEO riêng cho truyện.',
            'meta_keywords' => 'truyện chữ, tiểu thuyết',
        ]);

        $response = $this->get(route('stories.show', $story));

        $response->assertOk()
            ->assertSee('<title>Tựa đề SEO</title>', false)
            ->assertSee('name="keywords" content="truyện chữ, tiểu thuyết"', false)
            ->assertSee('rel="canonical" href="'.route('stories.show', $story).'"', false)
            ->assertSee('"@type":"Book"', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_sitemap_contains_published_chapter_urls(): void
    {
        $story = Story::factory()->create(['status_publish' => true]);
        $chapter = Chapter::factory()->for($story)->create(['status' => true]);

        $index = $this->get(route('sitemap.index'));

        $index->assertOk()
            ->assertSee(route('sitemap.chapters', ['page' => 1]), false);

        $this->get(route('sitemap.chapters', ['page' => 1]))
            ->assertOk()
            ->assertSee(route('chapters.show', ['story' => $story->slug, 'chapter' => $chapter->chapter_number]), false);
    }

    public function test_robots_txt_uses_the_configured_application_url(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml', false)
            ->assertDontSee('your-domain.com');
    }
}
