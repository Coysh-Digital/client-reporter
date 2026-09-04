<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleIntegration;
use App\Integrations\GoogleSearchConsole\SearchAnalyticsCollector;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Livewire\Reports\Builder;
use App\Models\Metric;
use App\Models\MetricSnapshot;
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
                if (in_array('date', $dimensions, true)) {
                    // Deliberately out of order to exercise the collector's sort.
                    return Http::response(['rows' => [
                        ['keys' => ['2026-08-02'], 'clicks' => 14, 'impressions' => 700],
                        ['keys' => ['2026-08-01'], 'clicks' => 10, 'impressions' => 500],
                    ]]);
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

    public function test_a_403_on_find_sites_surfaces_googles_specific_reason(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'at']);
            }

            return Http::response([
                'error' => ['code' => 403, 'message' => 'Google Search Console API has not been used in project 123 before or it is disabled.'],
            ], 403);
        });

        $client = new GoogleSearchConsoleClient('rt', 'https://a.test/', 'id', 'secret');

        try {
            $client->sites();
            $this->fail('Expected an IntegrationException.');
        } catch (IntegrationException $e) {
            $this->assertStringContainsString('enabled in your Google Cloud project', $e->getMessage());
            $this->assertStringContainsString('has not been used in project', $e->getMessage());
        }
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

        // Top landing pages are collected too, and the daily trend is sorted ascending.
        $payload = $result->snapshotPayload();
        $this->assertSame('https://a.test/', $payload['top_pages'][0]['label']);
        $this->assertSame(['2026-08-01', '2026-08-02'], array_column($payload['timeseries'], 'date'));
        $this->assertSame(10, $payload['timeseries'][0]['value']);
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

    public function test_search_block_renders_queries_landing_pages_and_a_trend(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $c = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'google_search_console', 'status' => ConnectionStatus::Connected,
        ]);

        Metric::query()->create([
            'site_integration_id' => $c->id, 'metric_key' => 'search.clicks',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'value' => 320, 'captured_at' => now(),
        ]);
        MetricSnapshot::query()->create([
            'site_integration_id' => $c->id, 'collector_key' => 'search',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'granularity' => 'range',
            'payload' => [
                'top_queries' => [['label' => 'northwind cafe', 'clicks' => 120, 'impressions' => 3000, 'ctr' => 4.0, 'position' => 3.1]],
                'top_pages' => [['label' => 'https://a.test/menu', 'clicks' => 80, 'impressions' => 2000, 'ctr' => 4.0, 'position' => 5.2]],
                'timeseries' => [['date' => '2026-08-01', 'value' => 10], ['date' => '2026-08-02', 'value' => 14]],
            ],
            'captured_at' => now(),
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'search.summary', 'position' => 0, 'heading' => 'Search']);

        $this->actingAs($admin)->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee('northwind cafe')
            ->assertSee('Top landing pages')
            ->assertSee('/menu')
            ->assertSee('Search clicks over time');
    }
}
