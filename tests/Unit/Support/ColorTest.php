<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Branding\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_contrast_is_symmetric_and_bounded(): void
    {
        $this->assertEqualsWithDelta(21.0, Color::contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(21.0, Color::contrast('#ffffff', '#000000'), 0.01);
        $this->assertEqualsWithDelta(1.0, Color::contrast('#123456', '#123456'), 0.001);
    }

    public function test_ink_on_picks_the_readable_foreground(): void
    {
        $this->assertSame('#ffffff', Color::inkOn('#1f1d1a'));
        $this->assertSame('#1f1d1a', Color::inkOn('#ffffff'));
        // A bright lime brand needs dark ink, not white.
        $this->assertSame('#1f1d1a', Color::inkOn('#a1c63e'));
    }

    public function test_readable_leaves_an_already_legible_colour_untouched(): void
    {
        // A dark secondary on light paper already passes — don't touch it.
        $this->assertSame('#8a6a2c', Color::readable('#8a6a2c', '#faf7ef', 4.5));
    }

    public function test_readable_darkens_a_light_colour_until_it_passes(): void
    {
        // A very pale blue as text on cream is unreadable; it must be nudged
        // until it clears the threshold.
        $before = Color::contrast('#d5ebf0', '#faf7ef');
        $this->assertLessThan(4.5, $before);

        $fixed = Color::readable('#d5ebf0', '#faf7ef', 4.5);

        $this->assertNotSame('#d5ebf0', $fixed);
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($fixed, '#faf7ef'));
    }

    public function test_readable_makes_a_light_divider_visible_on_a_light_brand_band(): void
    {
        // Pale blue divider on a lime brand band — must gain some contrast.
        $fixed = Color::readable('#d5ebf0', '#a1c63e', 3.0);

        $this->assertGreaterThanOrEqual(3.0, Color::contrast($fixed, '#a1c63e'));
    }

    public function test_invalid_input_is_returned_unchanged(): void
    {
        $this->assertSame('not-a-colour', Color::readable('not-a-colour', '#ffffff'));
    }

    public function test_parse_accepts_shorthand_hex(): void
    {
        $this->assertSame([255, 255, 255], Color::parse('#fff'));
        $this->assertSame([161, 198, 62], Color::parse('#a1c63e'));
        $this->assertNull(Color::parse('#12'));
    }
}
