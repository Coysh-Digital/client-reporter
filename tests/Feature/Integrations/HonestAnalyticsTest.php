<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\HonestAnalytics\HonestAnalyticsCollector;
use App\Integrations\HonestAnalytics\HonestAnalyticsIntegration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HonestAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'honest_analytics',
            'name' => 'Honest Analytics',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://wp.test'],
            'credentials' => ['secret' => 'shared-secret'],
        ]);
    }

    public function test_verify_succeeds_against_the_plugin(): void
    {
        Http::fake(['wp.test/*' => Http::response(['ok' => true, 'connector' => 'honest-analytics', 'version' => '1.0.0'])]);

        $result = (new HonestAnalyticsIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertSame('1.0.0', $result->meta['connector_version']);
    }

    public function test_verify_rejects_a_non_honest_analytics_response(): void
    {
        Http::fake(['wp.test/*' => Http::response(['ok' => true, 'connector' => 'wordpress'])]);

        $this->assertFalse((new HonestAnalyticsIntegration)->verify($this->connection())->ok);
    }

    public function test_it_signs_requests_to_the_plugin_namespace(): void
    {
        Http::fake(['wp.test/*' => Http::response(['ok' => true, 'connector' => 'honest-analytics'])]);

        (new HonestAnalyticsIntegration)->verify($this->connection());

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-CR-Signature')
            && str_contains($request->url(), '/wp-json/honest-analytics/v1/verify'));
    }

    public function test_collector_maps_the_report_to_analytics_metrics_and_snapshot(): void
    {
        Http::fake(['wp.test/*' => Http::response([
            'provider' => 'Honest Analytics',
            'metrics' => [
                'visitors' => 1200,
                'pageviews' => 3400,
                'visits' => 1500,
                'bounce_rate' => 42.5,
                'visit_duration' => 95,
            ],
            'timeseries' => [
                ['date' => '2026-08-01', 'value' => 40],
                ['date' => '2026-08-02', 'value' => 55],
            ],
            'top_pages' => [['label' => '/', 'visitors' => 300, 'pageviews' => 500]],
            'sources' => [['label' => 'Google', 'visitors' => 220]],
            'devices' => [['label' => 'Desktop', 'visitors' => 700]],
        ])]);

        $result = (new HonestAnalyticsCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(1200.0, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(3400.0, $metrics['analytics.pageviews']->value, 0.01);
        $this->assertEqualsWithDelta(42.5, $metrics['analytics.bounce_rate']->value, 0.01);
        $this->assertSame('%', $metrics['analytics.bounce_rate']->unit);
        $this->assertSame('seconds', $metrics['analytics.visit_duration']->unit);

        $snapshot = $result->snapshotPayload();
        $this->assertSame('Honest Analytics', $snapshot['provider']);
        $this->assertSame('Google', $snapshot['sources'][0]['label']);
        $this->assertSame(['date' => '2026-08-01', 'value' => 40], $snapshot['timeseries'][0]);
    }

    public function test_it_is_registered_as_an_analytics_integration(): void
    {
        $registry = app(IntegrationRegistry::class);
        $integration = $registry->find('honest_analytics');

        $this->assertNotNull($integration);
        $this->assertSame(IntegrationCategory::Analytics, $integration->manifest()->category);
    }
}
