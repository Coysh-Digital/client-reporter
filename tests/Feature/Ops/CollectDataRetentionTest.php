<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\ConnectionStatus;
use App\Models\Metric;
use App\Models\SiteIntegration;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CollectDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function metric(int $connectionId, string $capturedAt, string $periodStart, string $periodEnd): Metric
    {
        return Metric::create([
            'site_integration_id' => $connectionId,
            'metric_key' => 'analytics.visitors',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'value' => 10,
            'captured_at' => $capturedAt,
        ]);
    }

    public function test_it_prunes_metrics_older_than_the_retention_window(): void
    {
        // Not-connected so the collector loop skips it; we only exercise pruning.
        $conn = SiteIntegration::factory()->create(['status' => ConnectionStatus::NotConnected]);
        $old = $this->metric($conn->id, now()->subDays(200)->toDateTimeString(), '2026-01-01', '2026-01-31');
        $recent = $this->metric($conn->id, now()->subDays(5)->toDateTimeString(), '2026-02-01', '2026-02-28');

        app(Settings::class)->set('collection_retention_days', 90);

        Artisan::call('client-reporter:collect');

        $this->assertDatabaseMissing('metrics', ['id' => $old->id]);
        $this->assertDatabaseHas('metrics', ['id' => $recent->id]);
    }

    public function test_it_keeps_everything_when_no_retention_is_set(): void
    {
        $conn = SiteIntegration::factory()->create(['status' => ConnectionStatus::NotConnected]);
        $old = $this->metric($conn->id, now()->subDays(500)->toDateTimeString(), '2026-01-01', '2026-01-31');

        Artisan::call('client-reporter:collect');

        $this->assertDatabaseHas('metrics', ['id' => $old->id]);
    }
}
