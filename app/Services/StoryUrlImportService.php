<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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

    private const MAX_BODY_BYTES = 2_000_000;

    private function fetchDocument(string $url): \DOMDocument
    {
        // Resolve once, validate every address, then pin the request to the
        // validated IP so a second DNS lookup cannot point somewhere else
        // (DNS rebinding).
        [$host, $ips] = $this->resolveSafeTarget($url);

        $options = ['allow_redirects' => false];
        if (! filter_var($host, FILTER_VALIDATE_IP) && defined('CURLOPT_RESOLVE')) {
            $pin = str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':443:'.$pin]];
        }
        // Abort oversized downloads while streaming, not after the whole body is in memory.
        $options['on_headers'] = function ($response): void {
            if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BODY_BYTES) {
                throw new RuntimeException('Trang nguồn vượt quá giới hạn 2 MB.');
            }
        };
        $options['progress'] = function ($downloadTotal, $downloaded): void {
            if ($downloaded > self::MAX_BODY_BYTES) {
                throw new RuntimeException('Trang nguồn vượt quá giới hạn 2 MB.');
            }
        };

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'WebTruyenChuImporter/1.0 (+admin import; respect source permissions)'])
                ->withOptions($options)
                ->get($url);
        } catch (Throwable $exception) {
            $limit = $exception;
            while ($limit && ! str_contains($limit->getMessage(), 'giới hạn 2 MB')) {
                $limit = $limit->getPrevious();
            }

            throw new RuntimeException($limit ? 'Trang nguồn vượt quá giới hạn 2 MB.' : 'Không thể kết nối tới website nguồn.');
        }

        if ($response->redirect()) {
            throw new RuntimeException('Website chuyển hướng. Hãy dán URL đích HTTPS trực tiếp.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Không thể đọc website nguồn (HTTP '.$response->status().').');
        }

        $contentType = strtolower($response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html') && ! str_contains($contentType, 'xml')) {
            throw new RuntimeException('Website nguồn không trả về trang HTML.');
        }

        $html = $response->body();
        if (strlen($html) > self::MAX_BODY_BYTES) {
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

    /**
     * @return array{0: string, 1: list<string>} normalized host and its validated public IPs
     */
    private function resolveSafeTarget(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('Chỉ hỗ trợ URL HTTPS hợp lệ, không chứa thông tin đăng nhập.');
        }

        $host = rtrim(strtolower(trim($parts['host'], '[]')), '.');
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException('Địa chỉ máy chủ nội bộ không được phép.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);
        if ($ips === []) {
            throw new RuntimeException('Không phân giải được tên miền nguồn.');
        }

        // Every resolved address must be public, otherwise a mixed DNS answer
        // could still route the request to an internal service.
        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new RuntimeException('Địa chỉ máy chủ nguồn không được phép.');
            }
        }

        return [$host, $ips];
    }

    /**
     * @return list<string> all A and AAAA addresses for the host
     */
    protected function resolveHost(string $host): array
    {
        $ips = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $key) {
            foreach (@dns_get_record($host, $type) ?: [] as $record) {
                if (isset($record[$key])) {
                    $ips[] = $record[$key];
                }
            }
        }

        if ($ips === []) {
            $ips = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            return ! $this->isBlockedIpv4(ip2long($ip) ?: 0);
        }

        $bytes = array_values(unpack('C*', $packed));
        $embedded = null;
        if (array_slice($bytes, 0, 10) === array_fill(0, 10, 0) && $bytes[10] === 0xFF && $bytes[11] === 0xFF) {
            $embedded = array_slice($bytes, 12, 4);               // ::ffff:a.b.c.d
        } elseif (array_slice($bytes, 0, 12) === [0, 0x64, 0xFF, 0x9B, 0, 0, 0, 0, 0, 0, 0, 0]) {
            $embedded = array_slice($bytes, 12, 4);               // 64:ff9b::/96 NAT64
        } elseif ($bytes[0] === 0x20 && $bytes[1] === 0x02) {
            $embedded = array_slice($bytes, 2, 4);                // 2002::/16 6to4
        }
        if ($embedded !== null && $this->isBlockedIpv4(ip2long(implode('.', $embedded)) ?: 0)) {
            return false;
        }

        return ! (($bytes[0] & 0xFE) === 0xFC                      // fc00::/7 unique local
            || ($bytes[0] === 0xFE && ($bytes[1] & 0xC0) === 0x80)  // fe80::/10 link local
            || $bytes[0] === 0xFF);                                 // multicast
    }

    private function isBlockedIpv4(int $long): bool
    {
        foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
            '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15', '224.0.0.0/3'] as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return true;
            }
        }

        return false;
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
