<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\BackgroundTaskStatus;
use App\Enums\ConnectionStatus;
use App\Jobs\GenerateReport;
use App\Jobs\RunConnectorCollection;
use App\Livewire\Activity\QueueStatus;
use App\Livewire\Activity\Running;
use App\Livewire\Settings\Manage;
use App\Models\BackgroundTask;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BackgroundTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_a_report_records_a_task_that_finishes_with_progress(): void
    {
        Http::fake();
        $report = Report::factory()->for(Site::factory())->create(['title' => 'Acme — August']);
        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);

        GenerateReport::queueFor($report); // sync queue runs it inline

        $task = BackgroundTask::query()->where('kind', BackgroundTask::KIND_REPORT)->firstOrFail();
        $this->assertSame(BackgroundTaskStatus::Succeeded, $task->status);
        $this->assertSame('Generating report', $task->label);
        $this->assertSame('Acme — August', $task->description);
        $this->assertNotNull($task->progress_total);
        $this->assertSame($task->progress_total, $task->progress_current);
        $this->assertSame(100, $task->percent());
    }

    public function test_a_collection_job_records_a_named_task(): void
    {
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response([
            'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]]],
        ])]);

        $site = Site::factory()->create(['name' => 'Acme Site', 'url' => 'https://example.com']);
        $connection = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'pagespeed', 'status' => ConnectionStatus::Connected,
        ]);

        RunConnectorCollection::queueFor($connection, DateRange::thisMonth());

        $task = BackgroundTask::query()->where('kind', BackgroundTask::KIND_COLLECTION)->firstOrFail();
        $this->assertSame(BackgroundTaskStatus::Succeeded, $task->status);
        $this->assertSame('Collecting data', $task->label);
        $this->assertStringContainsString('Acme Site', (string) $task->description);
    }

    public function test_jobs_expose_human_display_names(): void
    {
        $report = Report::factory()->for(Site::factory())->create(['title' => 'Acme — August']);

        $this->assertSame('Generate report: Acme — August', (new GenerateReport($report))->displayName());
    }

    public function test_the_settings_page_saves_parallel_jobs(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('queue_workers', 4)
            ->call('save')
            ->assertHasNoErrors();

        app(Settings::class)->flush();
        $this->assertSame(4, (int) app(Settings::class)->get('queue_workers'));
    }

    public function test_parallel_jobs_is_capped_at_the_configured_maximum(): void
    {
        config(['client-reporter.queue.max_workers' => 5]);
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Manage::class)
            ->set('queue_workers', 999)
            ->call('save')
            ->assertHasErrors('queue_workers');
    }

    public function test_the_sidebar_widget_and_activity_panel_show_running_work(): void
    {
        $manager = User::factory()->manager()->create();
        BackgroundTask::query()->create([
            'kind' => BackgroundTask::KIND_REPORT,
            'task_key' => 'report:1',
            'label' => 'Generating report',
            'description' => 'Acme — August',
            'status' => BackgroundTaskStatus::Running,
            'progress_current' => 2,
            'progress_total' => 4,
            'started_at' => now(),
        ]);

        Livewire::actingAs($manager)->test(QueueStatus::class)
            ->assertSee('Activity')
            ->assertSee('Acme — August')
            ->assertSee('50%');

        Livewire::actingAs($manager)->test(Running::class)
            ->assertSee('Currently running')
            ->assertSee('Generating report');
    }

    public function test_stale_tasks_are_reaped_and_old_finished_tasks_pruned(): void
    {
        // A running task the worker abandoned long ago.
        $stale = BackgroundTask::query()->create([
            'kind' => BackgroundTask::KIND_COLLECTION, 'task_key' => 'collection:stale',
            'label' => 'Collecting data', 'status' => BackgroundTaskStatus::Running, 'started_at' => now(),
        ]);
        // A finished task well past the retention window.
        $old = BackgroundTask::query()->create([
            'kind' => BackgroundTask::KIND_REPORT, 'task_key' => 'report:old',
            'label' => 'Generating report', 'status' => BackgroundTaskStatus::Succeeded, 'finished_at' => now()->subDays(30),
        ]);
        DB::table('background_tasks')->where('id', $stale->id)->update(['updated_at' => now()->subHour()]);
        DB::table('background_tasks')->where('id', $old->id)->update(['updated_at' => now()->subDays(30)]);

        $this->artisan('client-reporter:collect')->assertSuccessful();

        $this->assertSame(BackgroundTaskStatus::Failed, $stale->refresh()->status);
        $this->assertDatabaseMissing('background_tasks', ['id' => $old->id]);
    }
}
