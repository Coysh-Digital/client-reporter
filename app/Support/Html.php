<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Minimal HTML allow-listing for short strings that integrations supply
 * (setup instructions). Everything is escaped except a handful of inline
 * emphasis tags, so a third-party extension in extensions/ cannot inject
 * markup or scripts into the admin.
 */
final class Html
{
    /** @var array<int, string> */
    private const INLINE_TAGS = ['strong', 'em', 'b', 'i', 'code', 'kbd'];

    public static function inline(string $html): HtmlString
    {
        $escaped = e($html);

        foreach (self::INLINE_TAGS as $tag) {
            $escaped = str_replace(["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"], ["<{$tag}>", "</{$tag}>"], $escaped);
        }

        return new HtmlString($escaped);
    }
}
