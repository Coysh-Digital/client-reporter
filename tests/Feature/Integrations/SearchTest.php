<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleIntegration;
use App\Integrations\GoogleSearchConsole\SearchAnalyticsCollector;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSearchConsole(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'at']);
            }
            if (str_contains($url, 'searchAnalytics/query')) {
                $dimensions = $request->data()['dimensions'] ?? [];
                if ($dimensions === []) {
                    return Http::response(['rows' => [['clicks' => 320, 'impressions' => 15000, 'ctr' => 0.0213, 'position' => 12.4]]]);
                }
                if (in_array('query', $dimensions, true)) {
                    return Http::response(['rows' => [['keys' => ['northwind cafe'], 'clicks' => 120, 'impressions' => 3000, 'ctr' => 0.04, 'position' => 3.1]]]);
                }

                return Http::response(['rows' => [['keys' => ['https://a.test/'], 'clicks' => 80, 'impressions' => 2000, 'ctr' => 0.04, 'position' => 5.2]]]);
            }

            return Http::response([]);
        });
    }

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'google_search_console', 'status' => ConnectionStatus::Connected,
            'credentials' => ['refresh_token' => 'rt'], 'settings' => ['site_url' => 'https://a.test/'],
        ]);
    }

    public function test_search_console_is_registered_in_a_search_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Search);
        $this->assertContains('google_search_console', $keys);
    }

    public function test_search_console_verify_and_collector(): void
    {
        $this->fakeSearchConsole();
        $connection = $this->connection();

        $this->assertTrue((new GoogleSearchConsoleIntegration)->verify($connection)->ok);

        $result = (new SearchAnalyticsCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertSame(320.0, (float) $metrics['search.clicks']->value);
        $this->assertSame(15000.0, (float) $metrics['search.impressions']->value);
        $this->assertEqualsWithDelta(2.1, $metrics['search.ctr']->value, 0.05);
        $this->assertEqualsWithDelta(12.4, $metrics['search.position']->value, 0.05);
        $this->assertNotEmpty($result->snapshotPayload()['top_queries']);
        $this->assertSame('northwind cafe', $result->snapshotPayload()['top_queries'][0]['label']);
    }

    public function test_search_block_available_for_a_search_console_site(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'google_search_console', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Search performance');
    }
}
