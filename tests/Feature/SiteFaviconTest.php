<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Site;
use App\Support\SiteFaviconFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_parses_the_declared_icon_link_and_caches_it(): void
    {
        $site = Site::factory()->create(['url' => 'https://northwind.test']);

        Http::fake([
            'northwind.test' => Http::response('<html><head><link rel="icon" href="/assets/icon.png"></head></html>'),
            'northwind.test/assets/icon.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertTrue((new SiteFaviconFetcher)->fetch($site));

        $site->refresh();
        $this->assertSame('site-favicons/'.$site->id.'.png', $site->favicon_path);
        $this->assertNotNull($site->favicon_fetched_at);
        Storage::disk('public')->assertExists($site->favicon_path);
        $this->assertNotNull($site->faviconUrl());
    }

    public function test_it_falls_back_to_the_default_favicon_ico(): void
    {
        $site = Site::factory()->create(['url' => 'https://acme.test']);

        Http::fake([
            'acme.test' => Http::response('<html><head></head></html>'),
            'acme.test/favicon.ico' => Http::response('ICODATA', 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $this->assertTrue((new SiteFaviconFetcher)->fetch($site));
        $this->assertSame('site-favicons/'.$site->id.'.ico', $site->refresh()->favicon_path);
    }

    public function test_it_does_not_store_a_non_image_response(): void
    {
        $site = Site::factory()->create(['url' => 'https://broken.test']);

        Http::fake([
            'broken.test' => Http::response('<html><head></head></html>'),
            'broken.test/favicon.ico' => Http::response('<html>Not found</html>', 404, ['Content-Type' => 'text/html']),
        ]);

        $this->assertFalse((new SiteFaviconFetcher)->fetch($site));

        $site->refresh();
        $this->assertNull($site->favicon_path);
        // The attempt is still recorded so it isn't hammered every run.
        $this->assertNotNull($site->favicon_fetched_at);
    }

    public function test_the_command_only_refetches_stale_or_new_sites(): void
    {
        $fresh = Site::factory()->create(['url' => 'https://fresh.test', 'favicon_fetched_at' => now()->subDay()]);
        $stale = Site::factory()->create(['url' => 'https://stale.test', 'favicon_fetched_at' => now()->subMonths(2)]);

        Http::fake([
            '*' => Http::response('OK', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('client-reporter:fetch-favicons')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'stale.test'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'fresh.test'));
    }
}
