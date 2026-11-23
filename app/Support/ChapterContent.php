<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Chapter bodies are stored as sanitized HTML (rich-text editor output).
 * Older chapters are plain text, so every reader of the content goes through
 * toHtml(), which also escapes plain text. Never echo raw chapter content.
 */
class ChapterContent
{
    private const TAG_PATTERN = '/<\/?(p|br|div|h[1-6]|ul|ol|li|blockquote|strong|b|em|i|u|s|strike|sub|sup|span|a|hr)\b[^>]*>/i';

    private static ?HTMLPurifier $purifier = null;

    public static function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match(self::TAG_PATTERN, $content);
    }

    /**
     * Sanitize rich-text HTML with a strict allowlist. Plain text is left as is.
     */
    public static function sanitize(string $content): string
    {
        if (! self::looksLikeHtml($content)) {
            return $content;
        }

        return trim(self::purifier()->purify($content));
    }

    /**
     * Safe HTML for display: sanitized rich text, or escaped plain text with line breaks kept.
     */
    public static function toHtml(?string $content): string
    {
        $content = (string) $content;

        if (self::looksLikeHtml($content)) {
            return self::sanitize($content);
        }

        $paragraphs = preg_split('/\R{2,}/u', trim($content)) ?: [];

        return implode("\n", array_map(
            fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
            array_filter($paragraphs, fn (string $paragraph): bool => trim($paragraph) !== ''),
        ));
    }

    /**
     * Visible text of the content, used for emptiness checks and summaries.
     */
    public static function plainText(?string $content): string
    {
        $text = self::looksLikeHtml((string) $content)
            ? strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $content))
            : (string) $content;

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $cache = storage_path('framework/cache/purifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', implode(',', [
            'p[class]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'hr',
            'h1[class]', 'h2[class]', 'h3[class]', 'h4[class]', 'blockquote[class]',
            'ul', 'ol', 'li[class]', 'a[href|title]', 'span[style|class]',
        ]));
        $config->set('Attr.AllowedClasses', [
            'ql-align-center', 'ql-align-right', 'ql-align-justify',
            'ql-indent-1', 'ql-indent-2', 'ql-indent-3', 'ql-indent-4', 'ql-indent-5', 'ql-indent-6', 'ql-indent-7', 'ql-indent-8',
            'ql-size-small', 'ql-size-large', 'ql-size-huge',
            'ql-font-serif', 'ql-font-monospace',
        ]);
        $config->set('CSS.AllowedProperties', ['color', 'background-color']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Core.EscapeNonASCIICharacters', false);

        return self::$purifier = new HTMLPurifier($config);
    }
}
