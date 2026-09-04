<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds small, self-contained SVG charts for reports. The output is embedded as
 * an <img src="data:image/svg+xml;..."> — dompdf renders SVG through its image
 * pipeline (php-svg-lib), not as inline markup, so this is the one way to get
 * real vector line graphs into the PDF (and it renders on the web too).
 */
class SvgChart
{
    /**
     * A filled line (area) chart. Colour is baked in as a hex string, since a
     * data-URI image can't read the page's CSS variables.
     *
     * @param  array<int, array{date?: string, value?: int|float}>  $series
     */
    public static function line(array $series, string $color, int $height = 160): string
    {
        $values = array_map(fn ($p): float => (float) ($p['value'] ?? 0), $series);
        $count = count($values);
        if ($count === 0) {
            return '';
        }

        $width = 560;
        $padX = 6;
        $padY = 10;
        $plotW = $width - $padX * 2;
        $plotH = $height - $padY * 2;

        $min = min(0.0, min($values));
        $max = max($values);
        $range = ($max - $min) ?: 1.0;

        $x = fn (int $i): float => $count === 1
            ? $padX + $plotW / 2
            : $padX + ($i / ($count - 1)) * $plotW;
        $y = fn (float $v): float => $padY + $plotH - (($v - $min) / $range) * $plotH;

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = round($x($i), 1).','.round($y($v), 1);
        }
        $line = implode(' ', $points);
        $baseY = round($padY + $plotH, 1);
        $area = round($x(0), 1).','.$baseY.' '.$line.' '.round($x($count - 1), 1).','.$baseY;

        // Faint horizontal gridlines at quarters of the plot height.
        $grid = '';
        foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $frac) {
            $gy = round($padY + $plotH * $frac, 1);
            $grid .= '<line x1="'.$padX.'" y1="'.$gy.'" x2="'.($padX + $plotW).'" y2="'.$gy.'" stroke="#e8e1d2" stroke-width="1"/>';
        }

        $color = self::safeColor($color);

        return '<svg width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" xmlns="http://www.w3.org/2000/svg">'
            .$grid
            .'<polygon points="'.$area.'" fill="'.$color.'" fill-opacity="0.15"/>'
            .'<polyline points="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>'
            .'</svg>';
    }

    /**
     * The chart as a data URI ready for an <img src>.
     *
     * @param  array<int, array{date?: string, value?: int|float}>  $series
     */
    public static function lineDataUri(array $series, string $color, int $height = 160): string
    {
        $svg = self::line($series, $color, $height);

        return $svg === '' ? '' : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Only allow a #hex colour into the SVG; fall back to a neutral otherwise.
     */
    private static function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#33406b';
    }
}
