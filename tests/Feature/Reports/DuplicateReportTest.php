<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Index;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportDuplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateReportTest extends TestCase
{
    use RefreshDatabase;

    private function bespokeReport(): Report
    {
        $report = Report::factory()->for(Site::factory())->create([
            'title' => 'Acme — September',
            'status' => 'final',
            'generated_at' => now(),
            'compare_previous' => true,
            'range_start' => '2026-09-01',
            'range_end' => '2026-09-30',
        ]);
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);
        $report->blocks()->create([
            'type' => 'text', 'position' => 1, 'heading' => 'Intro',
            'commentary' => 'A custom note', 'config' => ['body' => 'x'], 'ai_summary' => 'stale summary',
        ]);

        return $report;
    }

    public function test_duplicating_copies_sections_into_a_fresh_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $report = $this->bespokeReport();

        $component = Livewire::actingAs($manager)->test(Index::class)
            ->call('duplicate', $report->id);

        $copy = Report::query()->where('title', 'Copy of Acme — September')->firstOrFail();
        $component->assertRedirect(route('reports.edit', $copy));

        $this->assertNotSame($report->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->generated_at);
        $this->assertFalse($copy->scheduled);
        $this->assertSame($report->site_id, $copy->site_id);
        $this->assertSame('2026-09-01', $copy->range_start->toDateString());
        $this->assertTrue($copy->compare_previous);

        $this->assertSame(2, $copy->blocks()->count());
        $text = $copy->blocks()->where('type', 'text')->firstOrFail();
        $this->assertSame('A custom note', $text->commentary);
        $this->assertSame(['body' => 'x'], $text->config);
        $this->assertNull($text->ai_summary, 'the period-specific AI summary is not copied');
    }

    public function test_the_original_report_is_left_untouched(): void
    {
        $report = $this->bespokeReport();

        app(ReportDuplicator::class)->duplicate($report);

        $report->refresh();
        $this->assertSame('final', $report->status);
        $this->assertSame(2, $report->blocks()->count());
        $this->assertSame(2, Report::query()->count());
    }
}
