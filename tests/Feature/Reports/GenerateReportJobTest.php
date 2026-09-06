<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\GenerationStatus;
use App\Jobs\GenerateReport;
use App\Livewire\Reports\Builder;
use App\Livewire\Reports\Show;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Report generation collects from external services, so it runs as a queued
 * job the pages poll for. These tests cover the state machine around it.
 */
class GenerateReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): Report
    {
        $report = Report::factory()->for(Site::factory())->create();
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);

        return $report;
    }

    public function test_queueing_marks_the_report_and_dispatches_once(): void
    {
        Queue::fake();
        $report = $this->draft();
        $user = User::factory()->manager()->create();

        GenerateReport::queueFor($report, $user);
        GenerateReport::queueFor($report, $user);

        Queue::assertPushed(GenerateReport::class, 1);
        $this->assertSame(GenerationStatus::Queued, $report->refresh()->generation_status);
        $this->assertNotNull($report->generation_queued_at);
        $this->assertTrue($report->isGenerating());
    }

    public function test_the_job_generates_and_clears_its_state(): void
    {
        Http::fake();
        $report = $this->draft();
        $user = User::factory()->manager()->create();

        GenerateReport::queueFor($report, $user); // sync queue runs it inline

        $report->refresh();
        $this->assertNull($report->generation_status);
        $this->assertNotNull($report->generated_at);
        $this->assertSame('final', $report->status);
        $this->assertNotNull($report->latestRender);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.generated', 'user_id' => $user->id]);
    }

    public function test_a_failure_is_recorded_safely_and_can_be_retried(): void
    {
        $report = $this->draft();

        $generator = Mockery::mock(ReportGenerator::class);
        $generator->shouldReceive('generate')->once()->andThrow(new RuntimeException('secret=abc host=10.0.0.1'));
        $this->app->instance(ReportGenerator::class, $generator);

        try {
            GenerateReport::queueFor($report);
        } catch (RuntimeException) {
            // The sync queue rethrows after calling failed().
        }

        $report->refresh();
        $this->assertSame(GenerationStatus::Failed, $report->generation_status);
        $this->assertTrue($report->generationFailed());
        $this->assertStringNotContainsString('secret=abc', (string) $report->generation_error);
        $this->assertStringContainsString('RuntimeException', (string) $report->generation_error);

        // A retry from the builder re-queues it.
        Queue::fake();
        $manager = User::factory()->manager()->create();
        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->call('retryGeneration');

        Queue::assertPushed(GenerateReport::class, 1);
        $this->assertSame(GenerationStatus::Queued, $report->refresh()->generation_status);
    }

    public function test_the_builder_queues_generation_and_polls_to_the_report(): void
    {
        Http::fake();
        $report = $this->draft();
        $manager = User::factory()->manager()->create();

        $component = Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->call('generate')
            ->assertHasNoErrors();

        // On the sync queue generation has already finished by the time we poll.
        $component->call('pollGeneration')->assertRedirect(route('reports.show', $report));
        $this->assertNotNull($report->refresh()->generated_at);
    }

    public function test_the_builder_shows_the_overlay_while_queued(): void
    {
        $report = $this->draft();
        $report->forceFill(['generation_status' => GenerationStatus::Queued, 'generation_queued_at' => now()])->save();
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Generating your report')
            ->assertSee('wire:poll.2s="pollGeneration"', false)
            ->call('pollGeneration')
            ->assertNoRedirect();
    }

    public function test_the_report_page_queues_and_shows_progress(): void
    {
        Queue::fake();
        $report = $this->draft();
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Show::class, ['report' => $report])
            ->call('generate')
            ->assertSee('queued');

        Queue::assertPushed(GenerateReport::class, 1);
    }

    public function test_a_failed_scheduled_report_is_queued_again_by_the_scheduler(): void
    {
        Queue::fake();
        $site = Site::factory()->create(['report_frequency' => 'monthly']);
        $period = DateRange::lastMonth();
        $failed = Report::factory()->for($site)->create([
            'scheduled' => true,
            'range_start' => $period->start->toDateString(),
            'range_end' => $period->end->toDateString(),
            'generation_status' => GenerationStatus::Failed,
            'generation_error' => 'Provider down.',
        ]);

        $this->artisan('client-reporter:generate-scheduled')
            ->expectsOutputToContain('retried 1')
            ->assertSuccessful();

        Queue::assertPushed(GenerateReport::class, fn (GenerateReport $job): bool => $job->report->is($failed));
        $this->assertSame(1, Report::query()->where('site_id', $site->id)->count());
    }
}
