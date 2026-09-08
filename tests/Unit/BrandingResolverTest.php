<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Site;
use App\Support\Branding\BrandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): BrandingResolver
    {
        return app(BrandingResolver::class);
    }

    public function test_it_falls_back_to_defaults_when_nothing_is_configured(): void
    {
        $client = Client::factory()->create();

        $resolved = $this->resolver()->forClient($client);

        $this->assertSame(config('client-reporter.name'), $resolved->agencyName);
        $this->assertSame('#33406b', $resolved->primaryColor);
        $this->assertFalse($resolved->hasLogo());
    }

    public function test_global_branding_applies_to_a_client(): void
    {
        $global = $this->resolver()->global();
        $global->update(['agency_name' => 'Acme Digital', 'primary_color' => '#112233']);

        $client = Client::factory()->create();
        $resolved = $this->resolver()->forClient($client);

        $this->assertSame('Acme Digital', $resolved->agencyName);
        $this->assertSame('#112233', $resolved->primaryColor);
    }

    public function test_client_override_beats_global(): void
    {
        $this->resolver()->global()->update(['primary_color' => '#111111', 'agency_name' => 'Acme']);

        $client = Client::factory()->create();
        $client->branding()->create(['primary_color' => '#999999']);

        $resolved = $this->resolver()->forClient($client);

        // Overridden field wins; non-overridden field still inherits global.
        $this->assertSame('#999999', $resolved->primaryColor);
        $this->assertSame('Acme', $resolved->agencyName);
    }

    public function test_site_override_beats_client_and_global(): void
    {
        $this->resolver()->global()->update(['primary_color' => '#111111']);

        $client = Client::factory()->create();
        $client->branding()->create(['primary_color' => '#222222']);

        $site = Site::factory()->for($client)->create();
        $site->branding()->create(['primary_color' => '#333333']);

        $resolved = $this->resolver()->forSite($site);

        $this->assertSame('#333333', $resolved->primaryColor);
    }

    public function test_empty_override_values_do_not_clobber_inherited_values(): void
    {
        $this->resolver()->global()->update(['agency_name' => 'Inherited Co']);

        $client = Client::factory()->create();
        $client->branding()->create(['agency_name' => '', 'primary_color' => '#444444']);

        $resolved = $this->resolver()->forClient($client);

        $this->assertSame('Inherited Co', $resolved->agencyName);
        $this->assertSame('#444444', $resolved->primaryColor);
    }

    public function test_cover_customisation_cascades_and_falls_back(): void
    {
        $this->resolver()->global()->update([
            'primary_color' => '#101010',
            'report_cover_label' => 'Monthly update',
            'report_cover_color' => '#654321',
        ]);

        $client = Client::factory()->create();
        $resolved = $this->resolver()->forClient($client);

        $this->assertSame('Monthly update', $resolved->reportCoverLabel);
        $this->assertSame('#654321', $resolved->reportCoverColor);
        $this->assertSame('#654321', $resolved->coverColor());

        // With no dedicated cover colour, the banner uses the brand primary.
        $this->resolver()->global()->update(['report_cover_color' => null]);
        $this->assertSame('#101010', $this->resolver()->forClient(Client::factory()->create())->coverColor());
    }

    public function test_cover_toggles_default_on_and_a_stored_false_wins(): void
    {
        // Nothing set: every cover element defaults to shown.
        $resolved = $this->resolver()->forClient(Client::factory()->create());
        $this->assertTrue($resolved->reportCoverShowTagline);
        $this->assertTrue($resolved->reportCoverShowPeriod);
        $this->assertTrue($resolved->reportCoverShowContact);

        // A stored false is a real value the cascade must honour (not "empty").
        $this->resolver()->global()->update(['report_cover_show_tagline' => false]);
        $client = Client::factory()->create();
        $this->assertFalse($this->resolver()->forClient($client)->reportCoverShowTagline);

        // A site override flips it back on over the global false.
        $site = Site::factory()->for($client)->create();
        $site->branding()->create(['report_cover_show_tagline' => true]);
        $this->assertTrue($this->resolver()->forSite($site)->reportCoverShowTagline);
    }

    public function test_the_cover_renders_the_custom_label_and_honours_the_toggles(): void
    {
        $this->resolver()->global()->update([
            'tagline' => 'We build things',
            'report_cover_label' => 'Quarterly review',
            'report_cover_show_tagline' => false,
            'report_cover_show_period' => false,
            'report_cover_show_contact' => false,
        ]);

        $branding = $this->resolver()->forClient(Client::factory()->create());

        $html = view('reports.blocks.cover', [
            'data' => ['client' => 'Acme Ltd', 'site' => 'acme.example', 'period' => '1–31 August 2026', 'contact' => 'Sam Jones', 'prepared_on' => '1 Sep 2026'],
            'commentary' => null,
            'branding' => $branding,
            'icon' => 'document',
        ])->render();

        $this->assertStringContainsString('Quarterly review', $html);
        $this->assertStringNotContainsString('Website report', $html);
        $this->assertStringContainsString('acme.example', $html);
        // Hidden by the toggles.
        $this->assertStringNotContainsString('We build things', $html);
        $this->assertStringNotContainsString('1–31 August 2026', $html);
        $this->assertStringNotContainsString('Sam Jones', $html);
    }
}
