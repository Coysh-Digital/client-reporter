<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUptime(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response([
            'stat' => 'ok',
            'monitors' => [[
                'id' => 1, 'friendly_name' => 'Site', 'status' => 2,
                'average_response_time' => '210', 'custom_uptime_ranges' => '99.9000',
            ]],
        ])]);
    }

    private function reportWithUptime(): Report
    {
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'uptimerobot',
            'status' => ConnectionStatus::Connected,
        ]);

        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01',
            'range_end' => '2026-08-31',
            'compare_previous' => true,
        ]);

        foreach (['cover', 'uptime.summary', 'uptime.incidents', 'closing'] as $i => $type) {
            $report->blocks()->create(['type' => $type, 'position' => $i, 'heading' => ucfirst($type)]);
        }

        return $report;
    }

    public function test_generating_a_report_collects_data_and_freezes_a_render(): void
    {
        $this->fakeUptime();
        $report = $this->reportWithUptime();

        app(ReportGenerator::class)->generate($report);

        $report->refresh();
        $this->assertSame('final', $report->status);
        $this->assertNotNull($report->generated_at);
        $this->assertNotNull($report->latestRender);

        // Data was collected for both the report period and the comparison.
        $this->assertDatabaseHas('metrics', ['metric_key' => 'uptime.percentage']);

        // The frozen render carries resolved uptime data.
        $render = $report->latestRender;
        $summary = collect($render->data)->firstWhere('type', 'uptime.summary');
        $this->assertNotNull($summary);
        $this->assertTrue($summary['data']['has_data']);
        $uptimeMetric = collect($summary['data']['metrics'])->firstWhere('label', 'Uptime');
        $this->assertEqualsWithDelta(99.9, $uptimeMetric['current'], 0.01);
    }

    public function test_report_preview_renders_branded_document(): void
    {
        $this->fakeUptime();
        $admin = User::factory()->administrator()->create();
        $report = $this->reportWithUptime();

        $this->actingAs($admin)
            ->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee($report->site->client->name)
            ->assertSee('Website report');
    }

    public function test_builder_can_add_reorder_and_remove_blocks(): void
    {
        $manager = User::factory()->manager()->create();
        $report = Report::factory()->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $a = $report->blocks()->create(['type' => 'cover', 'position' => 0]);
        $b = $report->blocks()->create(['type' => 'text', 'position' => 1]);

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->call('addBlock', 'closing')
            ->call('reorder', [$b->id, $a->id])
            ->call('removeBlock', $a->id);

        $this->assertDatabaseMissing('report_blocks', ['id' => $a->id]);
        $this->assertSame(0, $b->refresh()->position);
        $this->assertDatabaseHas('report_blocks', ['report_id' => $report->id, 'type' => 'closing']);
    }

    public function test_hidden_blocks_are_excluded_from_the_render(): void
    {
        $this->fakeUptime();
        $report = $this->reportWithUptime();
        $report->blocks()->where('type', 'uptime.incidents')->update(['is_hidden' => true]);

        app(ReportGenerator::class)->generate($report);

        $types = collect($report->latestRender->data)->pluck('type');
        $this->assertFalse($types->contains('uptime.incidents'));
        $this->assertTrue($types->contains('uptime.summary'));
    }

    public function test_a_viewer_cannot_edit_the_builder(): void
    {
        $viewer = User::factory()->viewer()->create();
        $report = Report::factory()->create();

        $this->actingAs($viewer)->get(route('reports.edit', $report))->assertForbidden();
    }
}
