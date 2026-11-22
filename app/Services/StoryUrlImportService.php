<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class StoryUrlImportService
{
    public function preview(string $url): array
    {
        $document = $this->fetchDocument($url);
        $xpath = new \DOMXPath($document);
        $metadata = $this->metadata($xpath, $document);
        $chapters = $this->chapterLinks($xpath, $url);

        if ($metadata['title'] === '') {
            throw new RuntimeException('Không tìm thấy tên truyện. Website này có thể cần bộ phân tích riêng.');
        }

        if ($chapters === []) {
            throw new RuntimeException('Không tìm thấy danh sách chương. Website này có thể cần bộ phân tích riêng.');
        }

        return [
            'source_url' => $url,
            'host' => parse_url($url, PHP_URL_HOST),
            'title' => Str::limit($metadata['title'], 255, ''),
            'author' => Str::limit($metadata['author'], 150, ''),
            'description' => Str::limit($metadata['description'], 5000, ''),
            'chapters' => array_slice($chapters, 0, 200),
        ];
    }

    public function fetchChapter(string $url): string
    {
        $document = $this->fetchDocument($url);
        $xpath = new \DOMXPath($document);
        $queries = [
            '//article',
            '//*[@itemprop="articleBody"]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " chapter-content ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " reading-content ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]',
            '//main',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (! $nodes || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $clone = $node->cloneNode(true);
                if (! $clone instanceof \DOMElement) {
                    continue;
                }

                $remove = [];
                foreach (['script', 'style', 'nav', 'header', 'footer', 'aside', 'form', 'iframe', 'button'] as $tag) {
                    foreach ($clone->getElementsByTagName($tag) as $element) {
                        $remove[] = $element;
                    }
                }
                foreach ($remove as $element) {
                    $element->parentNode?->removeChild($element);
                }

                $paragraphs = [];
                foreach ($clone->getElementsByTagName('p') as $paragraph) {
                    $text = trim(preg_replace('/\s+/u', ' ', $paragraph->textContent) ?? '');
                    if ($text !== '') {
                        $paragraphs[] = $text;
                    }
                }

                $content = $paragraphs !== []
                    ? implode("\n\n", $paragraphs)
                    : trim(preg_replace('/[ \t]+/u', ' ', $clone->textContent) ?? '');

                if (mb_strlen($content) >= 80) {
                    return $this->toSafeHtml($content);
                }
            }
        }

        throw new RuntimeException('Không tìm thấy nội dung chương có thể đọc. Hãy kiểm tra website hoặc dùng bộ phân tích chuyên biệt.');
    }

    private function fetchDocument(string $url): \DOMDocument
    {
        $this->assertSafeUrl($url);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withHeaders(['User-Agent' => 'WebTruyenChuImporter/1.0 (+admin import; respect source permissions)'])
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if ($response->redirect()) {
            throw new RuntimeException('Website chuyển hướng. Hãy dán URL đích HTTPS trực tiếp.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Không thể đọc website nguồn (HTTP '.$response->status().').');
        }

        $html = $response->body();
        if (strlen($html) > 2_000_000) {
            throw new RuntimeException('Trang nguồn vượt quá giới hạn 2 MB.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Không thể phân tích HTML của website nguồn.');
        }

        return $document;
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! $parts || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('Chỉ hỗ trợ URL HTTPS hợp lệ, không chứa thông tin đăng nhập.');
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new RuntimeException('Địa chỉ máy chủ nội bộ không được phép.');
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Không phân giải được tên miền nguồn.');
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Địa chỉ máy chủ nguồn không được phép.');
        }
    }

    private function metadata(\DOMXPath $xpath, \DOMDocument $document): array
    {
        $readMeta = function (array $names) use ($xpath): string {
            foreach ($names as $name) {
                $nodes = $xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'"]/@content');
                if ($nodes && $nodes->length > 0) {
                    $value = trim($nodes->item(0)?->nodeValue ?? '');
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            return '';
        };

        $title = $readMeta(['og:title', 'twitter:title']);
        $description = $readMeta(['og:description', 'description']);
        $author = $readMeta(['author', 'book:author']);

        if ($title === '') {
            $nodes = $xpath->query('//h1 | //title');
            $title = trim($nodes?->item(0)?->textContent ?? '');
        }

        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }
            $json = json_decode($script->textContent, true);
            foreach ($this->flattenJsonLd($json) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $title = $title ?: (is_string($item['name'] ?? null) ? $item['name'] : '');
                $description = $description ?: (is_string($item['description'] ?? null) ? $item['description'] : '');
                $authorValue = $item['author']['name'] ?? $item['author'] ?? '';
                if (is_array($authorValue)) {
                    $authorValue = $authorValue[0]['name'] ?? '';
                }
                $author = $author ?: (is_string($authorValue) ? $authorValue : '');
            }
        }

        return [
            'title' => trim($title),
            'author' => trim($author),
            'description' => trim($description),
        ];
    }

    private function flattenJsonLd(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_merge(...array_map(fn ($item) => $this->flattenJsonLd($item), $value));
        }

        $items = [$value];
        if (isset($value['@graph'])) {
            $items = array_merge($items, $this->flattenJsonLd($value['@graph']));
        }

        return $items;
    }

    private function chapterLinks(\DOMXPath $xpath, string $sourceUrl): array
    {
        $nodes = $xpath->query('//a[@href]');
        $chapters = [];
        $seen = [];
        $number = 0;

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            $title = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($title === '' || ! preg_match('/(chương|chapter|hồi|quyển)\s*([0-9ivxlcdm]+)/iu', $title)) {
                continue;
            }

            $href = trim($node->attributes?->getNamedItem('href')?->nodeValue ?? '');
            $url = $this->absoluteUrl($href, $sourceUrl);
            if (! $url || isset($seen[$url])) {
                continue;
            }

            $number++;
            preg_match('/(?:chương|chapter|hồi|quyển)\s*([0-9]+)/iu', $title, $match);
            $chapters[] = [
                'title' => Str::limit($title, 255, ''),
                'url' => $url,
                'number' => isset($match[1]) ? (int) $match[1] : $number,
            ];
            $seen[$url] = true;
        }

        return $chapters;
    }

    private function absoluteUrl(string $href, string $sourceUrl): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return null;
        }

        $source = parse_url($sourceUrl);
        if (str_starts_with($href, 'https://')) {
            $target = parse_url($href);
            if (strtolower($target['host'] ?? '') !== strtolower($source['host'] ?? '')) {
                return null;
            }

            return $href;
        }

        if (str_starts_with($href, '//')) {
            return null;
        }

        $origin = 'https://'.$source['host'];
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = $source['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : dirname($path).'/';

        return $origin.rtrim($directory, '/').'/'.$href;
    }

    private function toSafeHtml(string $content): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($content)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $html[] = '<p>'.e($paragraph).'</p>';
            }
        }

        return implode("\n", $html);
    }
}
