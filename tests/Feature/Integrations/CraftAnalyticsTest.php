<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\CraftAnalytics\CraftAnalyticsCollector;
use App\Integrations\CraftAnalytics\CraftAnalyticsIntegration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CraftAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'craft_analytics',
            'name' => 'Craft Analytics',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://craft.test'],
            'credentials' => ['secret' => 'shared-secret'],
        ]);
    }

    public function test_verify_succeeds_against_the_plugin(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'craft-analytics', 'version' => '2.5.0'])]);

        $result = (new CraftAnalyticsIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertSame('2.5.0', $result->meta['connector_version']);
    }

    public function test_verify_rejects_a_non_craft_analytics_response(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'craft'])]);

        $this->assertFalse((new CraftAnalyticsIntegration)->verify($this->connection())->ok);
    }

    public function test_it_signs_requests_to_the_plugin_route(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'craft-analytics'])]);

        (new CraftAnalyticsIntegration)->verify($this->connection());

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-CR-Signature')
            && str_contains($request->url(), '/craft-analytics/v1/verify'));
    }

    public function test_collector_maps_the_report_to_analytics_metrics_and_snapshot(): void
    {
        Http::fake(['craft.test/*' => Http::response([
            'provider' => 'Craft Analytics',
            'metrics' => [
                'visitors' => 900,
                'pageviews' => 2600,
                'visits' => 1100,
                'bounce_rate' => 38.0,
                'visit_duration' => 120,
            ],
            'timeseries' => [
                ['date' => '2026-08-01', 'value' => 30],
                ['date' => '2026-08-02', 'value' => 45],
            ],
            'top_pages' => [['label' => '/', 'visitors' => 250, 'pageviews' => 400]],
            'sources' => [['label' => 'google.com', 'visitors' => 180]],
            'devices' => [['label' => 'Mobile', 'visitors' => 520]],
            'countries' => [['label' => 'GB', 'visitors' => 610]],
        ])]);

        $result = (new CraftAnalyticsCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(900.0, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(38.0, $metrics['analytics.bounce_rate']->value, 0.01);
        $this->assertSame('seconds', $metrics['analytics.visit_duration']->unit);

        $snapshot = $result->snapshotPayload();
        $this->assertSame('Craft Analytics', $snapshot['provider']);
        $this->assertSame('GB', $snapshot['countries'][0]['label']);
        $this->assertSame(['date' => '2026-08-02', 'value' => 45], $snapshot['timeseries'][1]);
    }

    public function test_it_is_registered_as_an_analytics_integration(): void
    {
        $integration = app(IntegrationRegistry::class)->find('craft_analytics');

        $this->assertNotNull($integration);
        $this->assertSame(IntegrationCategory::Analytics, $integration->manifest()->category);
    }
}
