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
     * @param  bool  $zeroBased  Start the y-axis at zero (good for counts). Set
     *                           false for metrics that hug the top of their range
     *                           (uptime %, Lighthouse scores) so variation shows.
     * @param  array<int, array{date?: string, value?: int|float}>  $compare  an
     *                                                                        optional previous-period series, drawn as a
     *                                                                        dashed second line (no area) on the same axis so
     *                                                                        the two periods can be compared day for day.
     */
    public static function line(array $series, string $color, int $height = 160, bool $zeroBased = true, array $compare = [], bool $axis = false): string
    {
        $values = array_map(fn ($p): float => (float) ($p['value'] ?? 0), $series);
        $count = count($values);
        if ($count === 0) {
            return '';
        }

        $compareValues = array_map(fn ($p): float => (float) ($p['value'] ?? 0), $compare);

        $width = 560;
        // A left gutter holds the y-axis labels when asked for; otherwise the
        // plot runs almost edge to edge as before.
        $left = $axis ? 44 : 6;
        $right = 6;
        $padY = 10;
        $plotW = $width - $left - $right;
        $plotH = $height - $padY * 2;

        // Scale the y-axis over both series so neither line is clipped.
        $allValues = array_merge($values, $compareValues);
        if ($zeroBased) {
            $min = min(0.0, min($allValues));
            $max = max($allValues);
        } else {
            $min = min($allValues);
            $max = max($allValues);
            $pad = (($max - $min) * 0.1) ?: 1.0;
            $min -= $pad;
            $max += $pad;
        }
        $range = ($max - $min) ?: 1.0;

        $y = fn (float $v): float => $padY + $plotH - (($v - $min) / $range) * $plotH;

        // Each series maps its own indices across the full width, so a shorter
        // previous period still overlays the current one end to end.
        $polyline = function (array $vals) use ($left, $plotW, $y): string {
            $n = count($vals);
            $points = [];
            foreach ($vals as $i => $v) {
                $px = $n === 1 ? $left + $plotW / 2 : $left + ($i / ($n - 1)) * $plotW;
                $points[] = round($px, 1).','.round($y($v), 1);
            }

            return implode(' ', $points);
        };

        $x = fn (int $i): float => $count === 1
            ? $left + $plotW / 2
            : $left + ($i / ($count - 1)) * $plotW;
        $line = $polyline($values);
        $baseY = round($padY + $plotH, 1);
        $area = round($x(0), 1).','.$baseY.' '.$line.' '.round($x($count - 1), 1).','.$baseY;

        // Faint horizontal gridlines at quarters of the plot height, with the
        // top/mid/bottom values labelled in the gutter when the axis is on.
        $grid = '';
        $labels = '';
        foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $frac) {
            $gy = round($padY + $plotH * $frac, 1);
            $grid .= '<line x1="'.$left.'" y1="'.$gy.'" x2="'.($left + $plotW).'" y2="'.$gy.'" stroke="#e8e1d2" stroke-width="1"/>';
            if ($axis && in_array($frac, [0.0, 0.5, 1.0], true)) {
                $val = $max - $frac * $range;
                $ty = $frac === 0.0 ? $gy + 9 : ($frac === 1.0 ? $gy - 3 : $gy + 3);
                // A tight range (e.g. uptime %) needs a decimal so the labels
                // don't collapse to the same rounded number.
                $labels .= '<text x="'.($left - 8).'" y="'.$ty.'" text-anchor="end" font-family="Helvetica, Arial, sans-serif" font-size="11" fill="#98938a">'.self::compact($val, $range < 3 ? 1 : 0).'</text>';
            }
        }

        $color = self::safeColor($color);

        // The previous period sits underneath the current line as a dashed,
        // muted stroke with no area fill, so the current period stays dominant.
        $compareLine = '';
        if (count($compareValues) > 0) {
            $compareLine = '<polyline points="'.$polyline($compareValues).'" fill="none" stroke="'.$color.'" stroke-opacity="0.45" stroke-width="1.75" stroke-dasharray="4 3" stroke-linejoin="round" stroke-linecap="round"/>';
        }

        return '<svg width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" xmlns="http://www.w3.org/2000/svg">'
            .$grid
            .'<polygon points="'.$area.'" fill="'.$color.'" fill-opacity="0.15"/>'
            .$compareLine
            .'<polyline points="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>'
            .$labels
            .'</svg>';
    }

    /**
     * A compact axis-label form of a value: 1.2k, 3.4M, or a rounded number
     * (one decimal only for small non-integers).
     */
    private static function compact(float $v, int $decimals = 0): string
    {
        $abs = abs($v);
        if ($abs >= 1000000) {
            return rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.').'M';
        }
        if ($abs >= 1000) {
            return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.').'k';
        }

        return number_format($v, $decimals);
    }

    /**
     * The chart as a data URI ready for an <img src>.
     *
     * @param  array<int, array{date?: string, value?: int|float}>  $series
     * @param  array<int, array{date?: string, value?: int|float}>  $compare
     */
    public static function lineDataUri(array $series, string $color, int $height = 160, bool $zeroBased = true, array $compare = [], bool $axis = true): string
    {
        $svg = self::line($series, $color, $height, $zeroBased, $compare, $axis);

        return $svg === '' ? '' : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * A donut gauge (0–100) as a data-URI `<img>`: a light track ring with a
     * coloured arc whose length is the score. The number is overlaid in HTML by
     * the caller, so this draws the ring only.
     */
    public static function gaugeDataUri(int $score, string $color, int $size = 64): string
    {
        $score = max(0, min(100, $score));
        $color = self::safeColor($color);
        // r chosen so the circumference is ~100, making the dash length == score.
        $r = 15.915;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" width="'.$size.'" height="'.$size.'">'
            .'<circle cx="18" cy="18" r="'.$r.'" fill="none" stroke="#ece5d6" stroke-width="3.2"/>'
            .'<circle cx="18" cy="18" r="'.$r.'" fill="none" stroke="'.$color.'" stroke-width="3.2" stroke-linecap="round" '
            .'stroke-dasharray="'.$score.' 100" transform="rotate(-90 18 18)"/>'
            .'<text x="18" y="21.3" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="9.5" font-weight="600" fill="'.$color.'">'.$score.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Only allow a #hex colour into the SVG; fall back to a neutral otherwise.
     */
    private static function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#33406b';
    }
}
