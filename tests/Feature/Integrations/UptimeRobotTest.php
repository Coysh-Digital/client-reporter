<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\UptimeRobot\MonitorsCollector;
use App\Integrations\UptimeRobot\UptimeRobotIntegration;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UptimeRobotTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMonitors(array $monitors): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response(['stat' => 'ok', 'monitors' => $monitors]),
        ]);
    }

    public function test_verify_succeeds_with_valid_key(): void
    {
        $this->fakeMonitors([['id' => 1, 'friendly_name' => 'Home', 'status' => 2]]);
        $connection = SiteIntegration::factory()->create(['credentials' => ['api_key' => 'valid']]);

        $result = (new UptimeRobotIntegration)->verify($connection);

        $this->assertTrue($result->ok);
    }

    public function test_verify_fails_gracefully_with_invalid_key(): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response(['stat' => 'fail', 'error' => ['message' => 'api_key is invalid']]),
        ]);
        $connection = SiteIntegration::factory()->create(['credentials' => ['api_key' => 'bad']]);

        $result = (new UptimeRobotIntegration)->verify($connection);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('invalid', $result->message);
    }

    public function test_collector_computes_uptime_incidents_and_snapshot(): void
    {
        $incidentTs = CarbonImmutable::create(2026, 8, 10, 12)->getTimestamp();

        $this->fakeMonitors([
            [
                'id' => 1,
                'friendly_name' => 'Marketing site',
                'url' => 'https://example.com',
                'status' => 2,
                'average_response_time' => '250',
                'custom_uptime_ranges' => '99.9500',
                'logs' => [
                    ['type' => 1, 'datetime' => $incidentTs, 'duration' => 600, 'reason' => ['detail' => 'Timeout']],
                ],
            ],
        ]);

        $connection = SiteIntegration::factory()->create(['credentials' => ['api_key' => 'valid']]);
        $result = (new MonitorsCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(99.95, $metrics['uptime.percentage']->value, 0.001);
        $this->assertSame(1, (int) $metrics['uptime.incidents']->value);
        $this->assertSame(600, (int) $metrics['uptime.downtime_seconds']->value);
        $this->assertSame(250, (int) $metrics['uptime.response_time_ms']->value);

        $payload = $result->snapshotPayload();
        $this->assertCount(1, $payload['monitors']);
        $this->assertCount(1, $payload['incidents']);
        $this->assertSame('Marketing site', $payload['incidents'][0]['monitor']);
    }

    public function test_incidents_outside_the_range_are_ignored(): void
    {
        $outsideTs = CarbonImmutable::create(2026, 7, 1)->getTimestamp();

        $this->fakeMonitors([
            [
                'id' => 1, 'friendly_name' => 'Site', 'status' => 2, 'custom_uptime_ranges' => '100.0000',
                'logs' => [['type' => 1, 'datetime' => $outsideTs, 'duration' => 100]],
            ],
        ]);

        $connection = SiteIntegration::factory()->create();
        $result = (new MonitorsCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));

        $incidents = collect($result->metrics())->firstWhere('key', 'uptime.incidents');
        $this->assertSame(0, (int) $incidents->value);
    }
}
