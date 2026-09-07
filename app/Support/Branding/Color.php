<?php

declare(strict_types=1);

namespace App\Support\Branding;

/**
 * Small colour-maths helper for keeping brand colours legible. Brand palettes
 * are chosen for identity, not contrast, so a light secondary can end up as
 * unreadable text on a light report surface — or an invisible divider on a
 * light brand band. These helpers derive a readable variant at render time and
 * leave the stored colour untouched, so the palette the agency picked is what
 * they still see wherever it has enough contrast to work.
 */
final class Color
{
    /** A near-black drawn from the report palette, used as the dark ink option. */
    private const DARK_INK = '#1f1d1a';

    private const LIGHT_INK = '#ffffff';

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    public static function parse(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex) === 1) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(
            static fn (int $c): int => max(0, min(255, $c)),
            $rgb,
        ));
    }

    /** WCAG relative luminance (0 = black, 1 = white). */
    public static function luminance(string $hex): float
    {
        $rgb = self::parse($hex) ?? [0, 0, 0];

        $channel = static function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
    }

    /** WCAG contrast ratio between two colours (1 = identical, 21 = black on white). */
    public static function contrast(string $a, string $b): float
    {
        $lighter = max(self::luminance($a), self::luminance($b));
        $darker = min(self::luminance($a), self::luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Blend $from towards $to by $amount (0 = $from, 1 = $to), keeping hue.
     */
    public static function mix(string $from, string $to, float $amount): string
    {
        $a = self::parse($from);
        $b = self::parse($to);
        if ($a === null || $b === null) {
            return $from;
        }

        $amount = max(0.0, min(1.0, $amount));

        return self::toHex([
            (int) round($a[0] + ($b[0] - $a[0]) * $amount),
            (int) round($a[1] + ($b[1] - $a[1]) * $amount),
            (int) round($a[2] + ($b[2] - $a[2]) * $amount),
        ]);
    }

    /** Whichever of dark ink / white reads best on the given background. */
    public static function inkOn(string $background): string
    {
        return self::contrast(self::LIGHT_INK, $background) >= self::contrast(self::DARK_INK, $background)
            ? self::LIGHT_INK
            : self::DARK_INK;
    }

    /**
     * $color adjusted just enough to reach $min contrast against $background,
     * keeping its hue by mixing towards black or white. Returns $color unchanged
     * when it already passes (or can't be parsed), or the most-contrasting
     * variant found when $min can't be reached.
     */
    public static function readable(string $color, string $background, float $min = 4.5): string
    {
        if (self::parse($color) === null) {
            return $color;
        }

        if (self::contrast($color, $background) >= $min) {
            return $color;
        }

        $best = $color;
        $bestContrast = self::contrast($color, $background);

        for ($step = 1; $step <= 20; $step++) {
            $amount = $step / 20;
            foreach ([self::DARK_INK, self::LIGHT_INK] as $target) {
                $candidate = self::mix($color, $target, $amount);
                $contrast = self::contrast($candidate, $background);

                if ($contrast >= $min) {
                    return $candidate;
                }

                if ($contrast > $bestContrast) {
                    $best = $candidate;
                    $bestContrast = $contrast;
                }
            }
        }

        return $best;
    }
}
