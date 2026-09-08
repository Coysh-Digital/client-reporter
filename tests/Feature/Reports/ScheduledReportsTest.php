<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Report;
use App\Models\Site;
use App\Support\Dashboard\DashboardData;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_report_for_a_scheduled_site_once_the_period_closes(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $site = Site::factory()->create(['report_frequency' => 'monthly']);

        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();

        $period = DateRange::lastMonth();
        $report = Report::query()->where('site_id', $site->id)->first();

        $this->assertNotNull($report);
        $this->assertTrue($report->scheduled);
        $this->assertNotNull($report->generated_at);
        $this->assertSame($period->start->toDateString(), $report->range_start->toDateString());
        $this->assertSame($period->end->toDateString(), $report->range_end->toDateString());
    }

    public function test_a_generation_delay_holds_the_report_until_the_delay_has_passed(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        // Delay of 4 days on a monthly site: on the 3rd of the month the
        // just-closed period is still within the settle window, so nothing
        // generates yet.
        $site = Site::factory()->create([
            'report_frequency' => 'monthly',
            'report_generation_delay_days' => 4,
        ]);

        $this->travelTo('2026-09-03 07:00:00');
        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();
        $this->assertSame(0, Report::query()->where('site_id', $site->id)->count());

        // On the 5th the delay has elapsed and August's report is generated.
        $this->travelTo('2026-09-05 07:00:00');
        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();

        $report = Report::query()->where('site_id', $site->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('2026-08-01', $report->range_start->toDateString());
        $this->assertSame('2026-08-31', $report->range_end->toDateString());

        $this->travelBack();
    }

    public function test_it_ignores_unscheduled_sites(): void
    {
        Site::factory()->create(['report_frequency' => 'none']);

        $this->artisan('client-reporter:generate-scheduled')->assertSuccessful();

        $this->assertSame(0, Report::query()->count());
    }

    public function test_it_does_not_duplicate_a_report_for_the_same_period(): void
    {
        Http::fake();
        $site = Site::factory()->create(['report_frequency' => 'monthly']);

        $this->artisan('client-reporter:generate-scheduled');
        $this->artisan('client-reporter:generate-scheduled');

        $this->assertSame(1, Report::query()->where('site_id', $site->id)->count());
    }

    public function test_the_dashboard_surfaces_a_generated_but_unsent_scheduled_report(): void
    {
        $site = Site::factory()->create(['report_frequency' => 'monthly']);
        Report::factory()->for($site)->create([
            'scheduled' => true,
            'generated_at' => now(),
            'status' => 'final',
        ]);

        $data = app(DashboardData::class)->build();

        $titles = array_column($data['needsAttention'], 'title');
        $this->assertTrue(
            collect($titles)->contains(fn (string $t): bool => str_contains($t, 'ready to send')),
            'a generated-but-unsent scheduled report should appear in Needs Attention'
        );
        $this->assertSame(1, $data['portfolio']['reportsToPrepare']);
    }

    public function test_a_sent_scheduled_report_is_not_flagged(): void
    {
        $site = Site::factory()->create(['report_frequency' => 'monthly']);
        $report = Report::factory()->for($site)->create([
            'scheduled' => true,
            'generated_at' => now(),
            'status' => 'final',
        ]);
        $report->shares()->create(['token_hash' => hash('sha256', 'token'), 'created_by' => null]);

        $data = app(DashboardData::class)->build();

        $this->assertSame(0, $data['portfolio']['reportsToPrepare']);
    }

    public function test_the_current_open_period_is_never_flagged_as_missing(): void
    {
        // The old bug flagged every active site for the current, still-open month.
        Site::factory()->create(['report_frequency' => 'monthly']); // scheduled, nothing generated yet
        Site::factory()->create(['report_frequency' => 'none']);    // not on a schedule at all

        $data = app(DashboardData::class)->build();

        $titles = array_column($data['needsAttention'], 'title');
        $this->assertFalse(collect($titles)->contains(fn (string $t): bool => str_contains($t, 'not created')));
        $this->assertFalse(collect($titles)->contains(fn (string $t): bool => str_contains($t, 'ready to send')));
    }
}
