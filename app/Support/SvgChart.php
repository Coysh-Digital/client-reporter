<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders a compact bar chart as inline SVG. Server-side and dependency-free, so
 * the identical markup displays on the web and through the dompdf PDF driver —
 * no client-side charting library or JavaScript required.
 */
class SvgChart
{
    /**
     * @param  array<int, array{date?: string, value?: int|float}>  $points
     */
    public static function bars(array $points, string $color = '#33406b', int $width = 680, int $height = 150): string
    {
        $points = array_values(array_filter($points, fn ($p) => isset($p['value'])));

        if ($points === []) {
            return '';
        }

        $max = max(1, (int) max(array_map(fn ($p) => (int) $p['value'], $points)));
        $count = count($points);
        $gap = 2;
        $barWidth = max(1.0, ($width - ($count - 1) * $gap) / $count);
        $chartHeight = $height - 22; // leave room for a baseline label

        $bars = '';
        foreach ($points as $i => $point) {
            $value = (int) $point['value'];
            $barHeight = ($value / $max) * $chartHeight;
            $x = $i * ($barWidth + $gap);
            $y = $chartHeight - $barHeight;
            $bars .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="1.5" fill="%s" opacity="0.85" />',
                $x, $y, $barWidth, max(0.0, $barHeight), htmlspecialchars($color, ENT_QUOTES),
            );
        }

        $first = htmlspecialchars((string) ($points[0]['date'] ?? ''), ENT_QUOTES);
        $last = htmlspecialchars((string) ($points[$count - 1]['date'] ?? ''), ENT_QUOTES);
        $labelY = $height - 4;

        return sprintf(
            '<svg viewBox="0 0 %d %d" width="100%%" height="%d" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">'
            .'%s'
            .'<line x1="0" y1="%d" x2="%d" y2="%d" stroke="#e6e1d8" stroke-width="1" />'
            .'<text x="0" y="%d" font-size="10" fill="#98938a">%s</text>'
            .'<text x="%d" y="%d" font-size="10" fill="#98938a" text-anchor="end">%s</text>'
            .'</svg>',
            $width, $height, $height,
            $bars,
            $chartHeight, $width, $chartHeight,
            $labelY, $first,
            $width, $labelY, $last,
        );
    }
}
