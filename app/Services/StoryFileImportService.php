<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Reads chapters from an uploaded .txt (split on "Chương N" headings) or .zip
 * (one .txt file per chapter, natural filename order). Nothing is extracted to
 * disk and the sizes are checked before reading, so a zip bomb cannot exhaust memory.
 */
class StoryFileImportService
{
    public const MAX_CHAPTERS = 500;

    public const MAX_ENTRY_BYTES = 2_000_000;

    public const MAX_TOTAL_BYTES = 30_000_000;

    private const HEADING = '/^\s*((?:chương|chuong|chapter|hồi|quyển)\s*([0-9]+|[ivxlcdm]+)\b[^\n]*)$/iu';

    /**
     * @return list<array{title: string, number: int, content: string}> content is plain text
     */
    public function chapters(string $path, string $extension, int $maxChapters = self::MAX_CHAPTERS): array
    {
        $chapters = match (strtolower($extension)) {
            'txt' => $this->fromText($this->readText((string) file_get_contents($path), '')),
            'zip' => $this->fromZip($path),
            default => throw new RuntimeException('Chỉ hỗ trợ tệp .txt hoặc .zip.'),
        };

        if ($chapters === []) {
            throw new RuntimeException('Không tìm thấy nội dung chương nào trong tệp.');
        }

        if (count($chapters) > $maxChapters) {
            throw new RuntimeException('Tệp có quá '.$maxChapters.' chương. Hãy chia nhỏ rồi nhập từng phần.');
        }

        return $this->numbered($chapters);
    }

    /**
     * Uploads that were never imported are removed after a day.
     */
    public function pruneUploads(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files('story-imports') as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Plain text to safe HTML paragraphs (every line becomes a paragraph).
     */
    public function toHtml(string $text): string
    {
        $paragraphs = [];
        foreach (preg_split('/\R+/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $paragraphs[] = '<p>'.e($line).'</p>';
            }
        }

        return implode("\n", $paragraphs);
    }

    /**
     * @return list<array{title: string, number: int|null, content: string}>
     */
    private function fromText(string $text): array
    {
        $chapters = [];
        $current = null;
        $preface = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match(self::HEADING, $line, $match)) {
                if ($current !== null) {
                    $chapters[] = $current;
                }
                $current = ['title' => trim($match[1]), 'number' => $this->chapterNumber($match[2]), 'lines' => []];

                continue;
            }

            if ($current === null) {
                $preface[] = $line;
            } else {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $chapters[] = $current;
        }

        if ($chapters === []) {
            // No headings: treat the whole file as a single chapter.
            return trim($text) === '' ? [] : [['title' => 'Chương 1', 'number' => 1, 'content' => trim($text)]];
        }

        // Text before the first heading (title page, blurb) is dropped on purpose.
        return array_values(array_filter(array_map(
            fn (array $chapter): array => [
                'title' => $chapter['title'],
                'number' => $chapter['number'],
                'content' => trim(implode("\n", $chapter['lines'])),
            ],
            $chapters,
        ), fn (array $chapter): bool => $chapter['content'] !== ''));
    }

    /**
     * @return list<array{title: string, number: int|null, content: string}>
     */
    private function fromZip(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Không mở được tệp zip.');
        }

        try {
            if ($zip->numFiles > self::MAX_CHAPTERS * 4) {
                throw new RuntimeException('Tệp zip chứa quá nhiều tệp.');
            }

            $entries = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                $base = basename($name);

                if ($name === '' || str_ends_with($name, '/') || str_starts_with($base, '.')
                    || str_contains($name, '__MACOSX') || strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'txt') {
                    continue;
                }

                $size = (int) ($stat['size'] ?? 0);
                if ($size > self::MAX_ENTRY_BYTES) {
                    throw new RuntimeException('Tệp "'.$base.'" lớn hơn 2 MB. Mỗi chương tối đa 2 MB.');
                }

                $total += $size;
                if ($total > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('Tổng dung lượng giải nén vượt quá 30 MB.');
                }

                $entries[] = ['index' => $i, 'name' => $name];
            }

            usort($entries, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

            $chapters = [];
            foreach ($entries as $entry) {
                $raw = $zip->getFromIndex($entry['index']);
                if ($raw === false) {
                    continue;
                }

                $text = trim($this->readText(substr($raw, 0, self::MAX_ENTRY_BYTES + 1), basename($entry['name'])));
                if ($text === '') {
                    continue;
                }

                $chapters[] = $this->chapterFromFile(basename($entry['name']), $text);
            }

            return $chapters;
        } finally {
            $zip->close();
        }
    }

    /**
     * One file is one chapter. A leading "Chương N: ..." line becomes the title,
     * otherwise the file name does.
     *
     * @return array{title: string, number: int|null, content: string}
     */
    private function chapterFromFile(string $fileName, string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $first = trim((string) array_shift($lines));

        if (preg_match(self::HEADING, $first, $match)) {
            return ['title' => $first, 'number' => $this->chapterNumber($match[2]), 'content' => trim(implode("\n", $lines))];
        }

        $stem = pathinfo($fileName, PATHINFO_FILENAME);
        $number = preg_match('/^\s*0*(\d+)/', $stem, $digits) ? (int) $digits[1] : null;
        $title = trim((string) preg_replace('/^\s*\d+\s*[-_.:)\]]*\s*/u', '', str_replace('_', ' ', $stem)));

        return ['title' => $title !== '' ? $title : 'Chương '.($number ?? '?'), 'number' => $number, 'content' => $text];
    }

    /**
     * @param  list<array{title: string, number: int|null, content: string}>  $chapters
     * @return list<array{title: string, number: int, content: string}>
     */
    private function numbered(array $chapters): array
    {
        $numbers = array_column($chapters, 'number');
        $usable = ! in_array(null, $numbers, true) && count(array_unique($numbers)) === count($numbers);

        foreach ($chapters as $index => &$chapter) {
            $chapter['number'] = $usable ? max(1, (int) $chapter['number']) : $index + 1;
            if ($chapter['title'] === 'Chương ?') {
                $chapter['title'] = 'Chương '.$chapter['number'];
            }
        }

        unset($chapter);

        return array_values($chapters);
    }

    private function chapterNumber(string $token): ?int
    {
        if (ctype_digit($token)) {
            return (int) $token;
        }

        // Roman numerals (I, IV, XII ...).
        $values = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100, 'd' => 500, 'm' => 1000];
        $total = 0;
        $chars = str_split(strtolower($token));
        foreach ($chars as $i => $char) {
            if (! isset($values[$char])) {
                return null;
            }
            $next = $values[$chars[$i + 1] ?? ''] ?? 0;
            $total += $values[$char] < $next ? -$values[$char] : $values[$char];
        }

        return $total > 0 ? $total : null;
    }

    private function readText(string $raw, string $label): string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw new RuntimeException(($label === '' ? 'Tệp' : 'Tệp "'.$label.'"').' không phải mã hóa UTF-8. Hãy lưu lại với mã hóa UTF-8 (trong Notepad: Save As, chọn Encoding UTF-8).');
        }

        return str_replace("\0", '', $raw);
    }
}
