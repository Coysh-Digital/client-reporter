<?php

declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use App\Enums\ConnectionStatus;
use App\Enums\SiteHealth;
use App\Models\Metric;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\Dashboard\SiteHealthResolver;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteHealthResolverTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = DateRange::custom('2026-08-01', '2026-08-31');
    }

    private function metric(SiteIntegration $conn, string $key, float $value): void
    {
        Metric::create([
            'site_integration_id' => $conn->id,
            'metric_key' => $key,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'value' => $value,
            'captured_at' => now(),
        ]);
    }

    public function test_a_connected_site_with_no_issues_is_healthy(): void
    {
        $site = Site::factory()->create(['is_active' => true]);
        SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::Connected]);

        $this->assertSame(SiteHealth::Healthy, app(SiteHealthResolver::class)->for($site, $this->period));
    }

    public function test_a_needs_attention_integration_makes_the_site_need_attention(): void
    {
        $site = Site::factory()->create(['is_active' => true]);
        SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::NeedsAttention]);

        $this->assertSame(SiteHealth::NeedsAttention, app(SiteHealthResolver::class)->for($site, $this->period));
    }

    public function test_an_errored_integration_makes_the_site_down(): void
    {
        $site = Site::factory()->create(['is_active' => true]);
        SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::Error]);

        $this->assertSame(SiteHealth::Down, app(SiteHealthResolver::class)->for($site, $this->period));
    }

    public function test_pending_cms_updates_flag_needs_attention(): void
    {
        $site = Site::factory()->create(['is_active' => true]);
        $conn = SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::Connected]);
        $this->metric($conn, 'cms.updates_total', 3);

        $this->assertSame(SiteHealth::NeedsAttention, app(SiteHealthResolver::class)->for($site, $this->period));
    }

    public function test_low_uptime_marks_the_site_down(): void
    {
        $site = Site::factory()->create(['is_active' => true]);
        $conn = SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::Connected]);
        $this->metric($conn, 'uptime.percentage', 90.0);

        $this->assertSame(SiteHealth::Down, app(SiteHealthResolver::class)->for($site, $this->period));
    }
}
