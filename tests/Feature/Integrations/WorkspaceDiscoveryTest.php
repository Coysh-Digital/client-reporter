<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Fathom\FathomIntegration;
use App\Integrations\GoogleAnalytics\GoogleAnalyticsIntegration;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleIntegration;
use App\Integrations\Matomo\MatomoIntegration;
use App\Integrations\Plausible\PlausibleIntegration;
use App\Integrations\Umami\UmamiIntegration;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every account-wide analytics/search integration correctly lists its sites for
 * the workspace connect flow, keyed to settings the collector already expects.
 */
class WorkspaceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_and_search_integrations_support_workspace_scope(): void
    {
        foreach ([
            new PlausibleIntegration,
            new FathomIntegration,
            new MatomoIntegration,
            new UmamiIntegration,
            new GoogleAnalyticsIntegration,
            new GoogleSearchConsoleIntegration,
        ] as $integration) {
            $this->assertTrue($integration->supportsWorkspaceScope(), $integration->key().' should support workspace scope');

            $siteScoped = collect($integration->configFields())->filter(fn ($f) => $f->scope === 'site');
            $this->assertNotEmpty($siteScoped, $integration->key().' should mark its per-site identifier field scope=site');
            $this->assertNotContains($siteScoped->first()->key, collect($integration->accountConfigFields())->pluck('key'));
        }
    }

    public function test_plausible_discovers_sites_by_domain(): void
    {
        Http::fake(['*plausible.io/api/v1/sites*' => Http::response([
            'sites' => [['domain' => 'northwind.test'], ['domain' => 'acme.test']],
        ])]);

        $workspace = new WorkspaceIntegration(['integration_key' => 'plausible', 'credentials' => ['api_token' => 'tok']]);
        $discovered = (new PlausibleIntegration)->discoverConnections($workspace);

        $this->assertCount(2, $discovered);
        $this->assertSame('northwind.test', $discovered[0]->url);
        $this->assertSame('northwind.test', $discovered[0]->settings['site_id']);
    }

    public function test_fathom_discovers_sites_using_name_as_the_url_guess(): void
    {
        Http::fake(['*api.usefathom.com/v1/sites*' => Http::response([
            ['id' => 'ABC123', 'name' => 'northwind.test'],
        ])]);

        $workspace = new WorkspaceIntegration(['integration_key' => 'fathom', 'credentials' => ['api_token' => 'tok']]);
        $discovered = (new FathomIntegration)->discoverConnections($workspace);

        $this->assertCount(1, $discovered);
        $this->assertSame('ABC123', $discovered[0]->externalId);
        $this->assertSame('northwind.test', $discovered[0]->url);
        $this->assertSame('ABC123', $discovered[0]->settings['site_id']);
    }

    public function test_umami_discovers_websites_by_domain(): void
    {
        Http::fake(['*/websites*' => Http::response(['data' => [
            ['id' => 'uuid-1', 'name' => 'Northwind', 'domain' => 'northwind.test'],
        ]])]);

        $workspace = new WorkspaceIntegration(['integration_key' => 'umami', 'credentials' => ['api_key' => 'key']]);
        $discovered = (new UmamiIntegration)->discoverConnections($workspace);

        $this->assertCount(1, $discovered);
        $this->assertSame('northwind.test', $discovered[0]->url);
        $this->assertSame('uuid-1', $discovered[0]->settings['website_id']);
    }

    public function test_matomo_discovers_sites_by_main_url(): void
    {
        Http::fake(['*/index.php*' => Http::response([
            ['idsite' => '1', 'name' => 'Northwind', 'main_url' => 'https://northwind.test/'],
        ])]);

        $workspace = new WorkspaceIntegration([
            'integration_key' => 'matomo',
            'credentials' => ['token' => 'tok'],
            'settings' => ['base_url' => 'https://analytics.test'],
        ]);
        $discovered = (new MatomoIntegration)->discoverConnections($workspace);

        $this->assertCount(1, $discovered);
        $this->assertSame('https://northwind.test/', $discovered[0]->url);
        $this->assertSame('1', $discovered[0]->settings['site_id']);
    }

    public function test_search_console_discovers_properties_and_strips_domain_prefix(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        Http::fake([
            '*oauth2.googleapis.com*' => Http::response(['access_token' => 'at']),
            '*webmasters/v3/sites*' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:northwind.test'],
                ['siteUrl' => 'https://acme.test/'],
            ]]),
        ]);

        $workspace = new WorkspaceIntegration(['integration_key' => 'google_search_console', 'credentials' => ['refresh_token' => 'rt']]);
        $discovered = (new GoogleSearchConsoleIntegration)->discoverConnections($workspace);

        $this->assertCount(2, $discovered);
        $this->assertSame('northwind.test', $discovered[0]->url);
        $this->assertSame('sc-domain:northwind.test', $discovered[0]->settings['site_url']);
        $this->assertSame('https://acme.test/', $discovered[1]->url);
    }

    public function test_google_analytics_discovers_properties_via_admin_api(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        Http::fake([
            '*oauth2.googleapis.com*' => Http::response(['access_token' => 'at']),
            '*accountSummaries*' => Http::response(['accountSummaries' => [
                ['propertySummaries' => [
                    ['property' => 'properties/123456789', 'displayName' => 'Northwind'],
                ]],
            ]]),
            '*properties/123456789/dataStreams*' => Http::response(['dataStreams' => [
                ['webStreamData' => ['defaultUri' => 'https://northwind.test']],
            ]]),
        ]);

        $workspace = new WorkspaceIntegration(['integration_key' => 'google_analytics', 'credentials' => ['refresh_token' => 'rt']]);
        $discovered = (new GoogleAnalyticsIntegration)->discoverConnections($workspace);

        $this->assertCount(1, $discovered);
        $this->assertSame('123456789', $discovered[0]->externalId);
        $this->assertSame('https://northwind.test', $discovered[0]->url);
        $this->assertSame('123456789', $discovered[0]->settings['property_id']);
    }

    public function test_workspace_setup_oauth_flow_redirects_then_discovers_after_callback(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['url' => 'https://northwind.test']);

        // First submit: no fields to fill for GA4, just redirects to Google.
        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['key' => 'google_analytics'])
            ->call('connect')
            ->assertRedirect();

        $workspace = WorkspaceIntegration::query()->firstWhere('integration_key', 'google_analytics');
        $this->assertNotNull($workspace);
        $this->assertNull($workspace->credential('refresh_token'));

        // Simulate the OAuth callback having stored a refresh token.
        $workspace->update(['credentials' => ['refresh_token' => 'rt'], 'status' => ConnectionStatus::Connected]);

        Http::fake([
            '*oauth2.googleapis.com*' => Http::response(['access_token' => 'at']),
            '*accountSummaries*' => Http::response(['accountSummaries' => [
                ['propertySummaries' => [['property' => 'properties/999', 'displayName' => 'Northwind']]],
            ]]),
            '*dataStreams*' => Http::response(['dataStreams' => [
                ['webStreamData' => ['defaultUri' => 'https://northwind.test']],
            ]]),
        ]);

        // Second visit: credentials exist, so this submit proceeds to discovery.
        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['workspace' => $workspace])
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertSet('assignments.0', $site->id);
    }
}
