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
}
