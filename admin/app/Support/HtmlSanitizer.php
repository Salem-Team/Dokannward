<?php

namespace App\Support;

/**
 * Allowlist HTML cleaner for CMS content written by staff.
 * Strips scripts/events while keeping the tags a rich text editor produces.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><a><span><div>';

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '' || $trimmed === '<p><br></p>' || $trimmed === '<p></p>') {
            return null;
        }

        $clean = strip_tags($trimmed, self::ALLOWED_TAGS);

        // Drop event handlers and javascript: URLs from attributes.
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*href\s*=\s*([\'"])\s*javascript:[^\'"]*\1/i', ' href="#"', $clean) ?? $clean;
        $clean = preg_replace('/\s*style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean) ?? $clean;

        $clean = self::repairBrokenMailtoLinks($clean);

        return trim($clean) !== '' ? $clean : null;
    }

    /**
     * Quill can split an email mid-edit:
     * `<a href="mailto:…">support@z</a>ibra.net` → only "support@z" is linked.
     * Merge the dangling domain back into the anchor and sync the mailto href.
     */
    private static function repairBrokenMailtoLinks(string $html): string
    {
        return (string) preg_replace_callback(
            '/(<a\b([^>]*\bhref\s*=\s*["\']mailto:)([^"\']+)(["\'][^>]*)>)([^<]*)(<\/a>)([A-Za-z0-9._%+-]+(?:\.[A-Za-z]{2,})+)/i',
            static function (array $m): string {
                $text = $m[5];
                $rest = $m[7];
                $merged = trim($text.$rest);

                if (! filter_var($merged, FILTER_VALIDATE_EMAIL)) {
                    return $m[0];
                }

                // Already a complete email inside the anchor — leave alone.
                if (preg_match('/@[^\s@]+\.[A-Za-z]{2,}$/', trim($text))) {
                    return $m[0];
                }

                return '<a'.$m[2].$merged.$m[4].'>'.$merged.$m[6];
            },
            $html
        );
    }
}
