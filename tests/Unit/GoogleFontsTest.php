<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GoogleFonts;
use PHPUnit\Framework\TestCase;

class GoogleFontsTest extends TestCase
{
    public function test_it_knows_catalogue_membership_and_category(): void
    {
        $this->assertTrue(GoogleFonts::has('Source Serif 4'));
        $this->assertFalse(GoogleFonts::has('Definitely Not A Font'));
        $this->assertSame('serif', GoogleFonts::category('Source Serif 4'));
        $this->assertSame('sans-serif', GoogleFonts::category('Hanken Grotesk'));
    }

    public function test_it_builds_a_stack_with_a_category_fallback(): void
    {
        $stack = GoogleFonts::cssStack('Source Serif 4');

        $this->assertStringStartsWith("'Source Serif 4', ", $stack);
        $this->assertStringContainsString('serif', $stack);
    }

    public function test_it_extracts_a_known_family_from_a_stack(): void
    {
        $this->assertSame('Source Serif 4', GoogleFonts::extractFamily("'Source Serif 4', Georgia, serif"));
        $this->assertSame('Merriweather', GoogleFonts::extractFamily('Merriweather'));
        $this->assertNull(GoogleFonts::extractFamily('Comic Sans MS, sans-serif'));
        $this->assertNull(GoogleFonts::extractFamily(null));
    }

    public function test_it_builds_a_google_fonts_url_only_for_known_families(): void
    {
        $url = GoogleFonts::googleUrl(['Source Serif 4', 'Hanken Grotesk', null, 'Unknown']);

        $this->assertNotNull($url);
        $this->assertStringContainsString('family=Source+Serif+4', $url);
        $this->assertStringContainsString('family=Hanken+Grotesk', $url);
        $this->assertStringContainsString('display=swap', $url);
        $this->assertStringNotContainsString('Unknown', $url);

        $this->assertNull(GoogleFonts::googleUrl(['Not A Font', null]));
    }
}
