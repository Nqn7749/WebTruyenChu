<?php

return [
    /*
     * When true, only hosts listed in "sources" can be imported. Recommended
     * for production: every source must be added deliberately by a developer
     * after confirming the site owner permits automated collection.
     */
    'require_allowlist' => env('STORY_IMPORT_REQUIRE_ALLOWLIST', false),

    /*
     * Optional per-host parsing profiles (XPath). Keys are lowercase hosts.
     * Missing keys fall back to the generic parser.
     *
     * 'source.example.org' => [
     *     'title'           => '//h1[@class="story-title"]',
     *     'author'          => '//*[@class="story-author"]',
     *     'description'     => '//*[@id="story-intro"]',
     *     'chapter_links'   => '//ul[@id="chapters"]//a[@href]',
     *     'chapter_content' => '//div[@id="chapter-content"]',
     *     'remove'          => ['//div[@class="ads"]', '//script'],
     * ],
     */
    'sources' => [],
];
