<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SvgChart;
use PHPUnit\Framework\TestCase;

class SvgChartTest extends TestCase
{
    public function test_it_builds_a_line_and_area_svg_from_a_series(): void
    {
        $svg = SvgChart::line([
            ['date' => '2026-08-01', 'value' => 100],
            ['date' => '2026-08-02', 'value' => 250],
            ['date' => '2026-08-03', 'value' => 180],
        ], '#d97a34');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<polyline', $svg);
        $this->assertStringContainsString('<polygon', $svg);
        $this->assertStringContainsString('#d97a34', $svg);
    }

    public function test_an_empty_series_produces_nothing(): void
    {
        $this->assertSame('', SvgChart::line([], '#d97a34'));
        $this->assertSame('', SvgChart::lineDataUri([], '#d97a34'));
    }

    public function test_the_data_uri_is_a_base64_svg(): void
    {
        $uri = SvgChart::lineDataUri([['value' => 1], ['value' => 2]], '#d97a34');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertStringContainsString('<svg', base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))));
    }

    public function test_a_non_hex_colour_falls_back_safely(): void
    {
        // Guards against anything but a #hex colour reaching the SVG markup.
        $svg = SvgChart::line([['value' => 5]], 'red"/><script>');

        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('#33406b', $svg);
    }
}
