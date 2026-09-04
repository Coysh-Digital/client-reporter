<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Reporting\Support\Insight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every summary block carries an auto-generated, plain-English "at a glance"
 * sentence (built from data the block already resolved) shown as a callout
 * above its metric grid — mirroring the reference report's per-section
 * summary. This is generated, never staff-written (that's $commentary).
 */
class InsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_insight_headline_describes_growth(): void
    {
        $sentence = Insight::headline('visitors', 14310.0, 13540.0);

        $this->assertSame('14,310 visitors, up 5.7% from the prior period.', $sentence);
    }

    public function test_insight_headline_describes_decline(): void
    {
        $sentence = Insight::headline('clicks from Google search', 2140.0, 2450.0);

        $this->assertSame('2,140 clicks from Google search, down 12.7% from the prior period.', $sentence);
    }

    public function test_insight_headline_without_a_comparison(): void
    {
        $sentence = Insight::headline('visitors', 14310.0, null);

        $this->assertSame('14,310 visitors this period.', $sentence);
    }

    public function test_insight_headline_returns_null_with_no_current_value(): void
    {
        $this->assertNull(Insight::headline('visitors', null, 100.0));
    }

    public function test_uptime_and_store_reports_render_a_summary_callout(): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response(['stat' => 'ok', 'monitors' => [[
                'id' => 1, 'friendly_name' => 'Site', 'status' => 2,
                'average_response_time' => '210', 'custom_uptime_ranges' => '99.9200',
            ]]]),
        ]);

        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'uptimerobot', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
        ]);
        foreach (['cover', 'uptime.summary'] as $i => $type) {
            $report->blocks()->create(['type' => $type, 'position' => $i, 'heading' => ucfirst($type)]);
        }

        app(ReportGenerator::class)->generate($report);
        $report->refresh();

        $summary = collect($report->latestRender->data)->firstWhere('type', 'uptime.summary');
        $this->assertStringContainsString('99.92% uptime', $summary['data']['insight']);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)
            ->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee('Summary')
            ->assertSee('99.92% uptime', false);
    }
}
