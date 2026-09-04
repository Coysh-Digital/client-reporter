<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A curated catalogue of Google Fonts offered in the branding picker, plus
 * helpers to turn a chosen family into a CSS stack and a Google Fonts URL.
 *
 * Reports store a full font stack (e.g. "'Source Serif 4', Georgia, serif") so
 * the document template can interpolate it directly and still fall back sensibly
 * where the web font cannot load (e.g. the dompdf PDF driver). extractFamily()
 * recovers the Google family from a stack so the document can load it.
 */
class GoogleFonts
{
    /**
     * family => category. Category drives the fallback generic + grouping.
     *
     * @var array<string, string>
     */
    private const CATALOGUE = [
        // Serif
        'Source Serif 4' => 'serif',
        'Fraunces' => 'serif',
        'Playfair Display' => 'serif',
        'Merriweather' => 'serif',
        'Lora' => 'serif',
        'PT Serif' => 'serif',
        'Libre Baskerville' => 'serif',
        'Cormorant Garamond' => 'serif',
        'EB Garamond' => 'serif',
        'Crimson Pro' => 'serif',
        'Bitter' => 'serif',
        'Noto Serif' => 'serif',
        'Roboto Slab' => 'serif',
        'Spectral' => 'serif',
        'DM Serif Display' => 'serif',
        'Newsreader' => 'serif',
        'Domine' => 'serif',
        'Cardo' => 'serif',
        'Zilla Slab' => 'serif',
        'Frank Ruhl Libre' => 'serif',

        // Sans-serif
        'Hanken Grotesk' => 'sans-serif',
        'Inter' => 'sans-serif',
        'Roboto' => 'sans-serif',
        'Open Sans' => 'sans-serif',
        'Lato' => 'sans-serif',
        'Montserrat' => 'sans-serif',
        'Poppins' => 'sans-serif',
        'Raleway' => 'sans-serif',
        'Work Sans' => 'sans-serif',
        'Nunito' => 'sans-serif',
        'Nunito Sans' => 'sans-serif',
        'Source Sans 3' => 'sans-serif',
        'PT Sans' => 'sans-serif',
        'Mulish' => 'sans-serif',
        'Rubik' => 'sans-serif',
        'DM Sans' => 'sans-serif',
        'Manrope' => 'sans-serif',
        'Karla' => 'sans-serif',
        'Figtree' => 'sans-serif',
        'Plus Jakarta Sans' => 'sans-serif',
        'Instrument Sans' => 'sans-serif',
        'Archivo' => 'sans-serif',
        'Barlow' => 'sans-serif',
        'Libre Franklin' => 'sans-serif',
        'IBM Plex Sans' => 'sans-serif',
        'Space Grotesk' => 'sans-serif',
        'Outfit' => 'sans-serif',
        'Albert Sans' => 'sans-serif',
        'Onest' => 'sans-serif',
        'Sora' => 'sans-serif',

        // Display
        'Oswald' => 'display',
        'Bebas Neue' => 'display',
        'Anton' => 'display',
        'Abril Fatface' => 'display',
        'Josefin Sans' => 'display',
        'Comfortaa' => 'display',

        // Monospace
        'JetBrains Mono' => 'monospace',
        'Fira Code' => 'monospace',
        'IBM Plex Mono' => 'monospace',
        'Space Mono' => 'monospace',
        'Roboto Mono' => 'monospace',
        'Source Code Pro' => 'monospace',
    ];

    /** Generic fallback stack per category. */
    private const FALLBACKS = [
        'serif' => "Georgia, 'Times New Roman', serif",
        'sans-serif' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        'display' => "'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        'monospace' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
    ];

    /**
     * @return array<int, array{family: string, category: string}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::CATALOGUE as $family => $category) {
            $out[] = ['family' => $family, 'category' => $category];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public static function families(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function has(string $family): bool
    {
        return isset(self::CATALOGUE[$family]);
    }

    public static function category(string $family): string
    {
        return self::CATALOGUE[$family] ?? 'sans-serif';
    }

    /**
     * A full CSS font-family stack for a chosen family, with a category-matched
     * generic fallback.
     */
    public static function cssStack(string $family): string
    {
        $family = trim($family);
        if ($family === '') {
            return self::FALLBACKS['sans-serif'];
        }

        $fallback = self::FALLBACKS[self::category($family)] ?? self::FALLBACKS['sans-serif'];

        return "'{$family}', {$fallback}";
    }

    /**
     * Recover the leading family from a stored stack (or bare family), returning
     * it only when it is a known Google font we can load.
     */
    public static function extractFamily(?string $stack): ?string
    {
        if ($stack === null || trim($stack) === '') {
            return null;
        }

        $first = trim(explode(',', $stack)[0]);
        $first = trim($first, " \t\n\r\0\x0B\"'");

        return self::has($first) ? $first : null;
    }

    /**
     * A Google Fonts CSS2 URL for the given families (deduplicated), or null if
     * none are loadable Google fonts. Requests the weights the reports use.
     *
     * @param  array<int, string|null>  $families
     */
    public static function googleUrl(array $families): ?string
    {
        $loadable = [];
        foreach ($families as $family) {
            if ($family !== null && self::has($family) && ! in_array($family, $loadable, true)) {
                $loadable[] = $family;
            }
        }

        if ($loadable === []) {
            return null;
        }

        $parts = array_map(
            fn (string $f): string => 'family='.str_replace(' ', '+', $f).':wght@400;500;600;700',
            $loadable,
        );

        return 'https://fonts.googleapis.com/css2?'.implode('&', $parts).'&display=swap';
    }
}
