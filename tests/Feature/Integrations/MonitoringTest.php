<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\BetterUptime\BetterUptimeIntegration;
use App\Integrations\BetterUptime\MonitorsCollector;
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

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_monitoring_providers_are_registered(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Monitoring);

        $this->assertContains('uptimerobot', $keys);
        $this->assertContains('better_uptime', $keys);
    }

    public function test_better_uptime_verify_and_collector(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/sla') => Http::response(['data' => ['attributes' => ['availability' => 99.95, 'total_downtime' => 120, 'number_of_incidents' => 2]]]),
                str_contains($url, '/incidents') => Http::response(['data' => [['attributes' => ['name' => 'Main site', 'cause' => 'Timeout', 'started_at' => '2026-08-12T10:00:00Z', 'resolved_at' => '2026-08-12T10:26:00Z']]]]),
                str_contains($url, '/monitors') => Http::response(['data' => [['id' => '1', 'attributes' => ['pronounceable_name' => 'Main site', 'url' => 'https://a.test', 'status' => 'up']]]]),
                default => Http::response([]),
            };
        });

        $connection = SiteIntegration::factory()->create([
            'integration_key' => 'better_uptime', 'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'tok'], 'settings' => [],
        ]);

        $this->assertTrue((new BetterUptimeIntegration)->verify($connection)->ok);

        $result = (new MonitorsCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(99.95, $metrics['uptime.percentage']->value, 0.01);
        $this->assertSame(2.0, (float) $metrics['uptime.incidents']->value);
        $this->assertSame(120.0, (float) $metrics['uptime.downtime_seconds']->value);
        $snapshot = $result->snapshotPayload();
        $this->assertNotEmpty($snapshot['monitors']);
        $this->assertSame(1560, $snapshot['incidents'][0]['duration_seconds']); // 26 minutes
    }

    public function test_uptime_blocks_are_available_for_any_monitoring_provider(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'better_uptime', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Uptime summary')
            ->assertSee('Incidents');
    }
}
