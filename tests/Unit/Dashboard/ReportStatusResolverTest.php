<?php

declare(strict_types=1);

namespace Tests\Unit\Dashboard;

use App\Enums\ReportPeriodStatus;
use App\Models\Report;
use App\Models\ReportShare;
use App\Models\Site;
use App\Support\Dashboard\ReportStatusResolver;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = DateRange::custom('2026-08-01', '2026-08-31');
    }

    private function resolve(Site $site): ReportPeriodStatus
    {
        return app(ReportStatusResolver::class)->forSites(collect([$site]), $this->period)[$site->id]['status'];
    }

    public function test_a_site_with_no_report_is_not_started(): void
    {
        $site = Site::factory()->create();

        $this->assertSame(ReportPeriodStatus::NotStarted, $this->resolve($site));
    }

    public function test_an_ungenerated_report_is_a_draft(): void
    {
        $site = Site::factory()->create();
        Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
            'status' => 'draft', 'generated_at' => null,
        ]);

        $this->assertSame(ReportPeriodStatus::Draft, $this->resolve($site));
    }

    public function test_a_generated_unshared_report_is_ready(): void
    {
        $site = Site::factory()->create();
        Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
            'status' => 'final', 'generated_at' => now(),
        ]);

        $this->assertSame(ReportPeriodStatus::Ready, $this->resolve($site));
    }

    public function test_a_generated_shared_report_is_sent(): void
    {
        $site = Site::factory()->create();
        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
            'status' => 'final', 'generated_at' => now(),
        ]);
        ReportShare::create([
            'report_id' => $report->id,
            'token_hash' => hash('sha256', 'tok'),
            'views' => 0,
        ]);

        $this->assertSame(ReportPeriodStatus::Sent, $this->resolve($site));
    }
}
