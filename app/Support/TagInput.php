<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parses the free-text "Tags" field (comma, semicolon or newline separated).
 */
class TagInput
{
    public const MAX_TAGS = 10;

    public const MAX_NAME_LENGTH = 30;

    /**
     * @return array<string, string> tag name keyed by its slug (duplicates by slug collapsed)
     */
    public static function parse(?string $input): array
    {
        $tags = [];

        foreach (preg_split('/[,;\n\r]+/u', (string) $input) ?: [] as $raw) {
            $name = trim(preg_replace('/\s+/u', ' ', str_replace(['<', '>'], '', $raw)) ?? '');
            $name = ltrim($name, "# \t");

            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $tags[$slug] ??= $name;
        }

        return $tags;
    }

    /**
     * Returns an error message, or null when the input is acceptable.
     */
    public static function validate(?string $input): ?string
    {
        $tags = self::parse($input);

        if (count($tags) > self::MAX_TAGS) {
            return 'Chỉ được thêm tối đa '.self::MAX_TAGS.' tag.';
        }

        foreach ($tags as $name) {
            if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
                return 'Mỗi tag tối đa '.self::MAX_NAME_LENGTH.' ký tự: "'.Str::limit($name, 20).'".';
            }
        }

        return null;
    }
}
