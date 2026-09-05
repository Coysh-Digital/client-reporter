<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\ConnectionStatus;
use App\Jobs\FetchSiteFavicon;
use App\Jobs\RunConnectorCollection;
use App\Jobs\SyncBillingConnection;
use App\Livewire\Activity\Index;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\CollectorRun;
use App\Models\Report;
use App\Models\ReportRender;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BackgroundWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_jobs_are_unique_per_connection_and_period(): void
    {
        Queue::fake();
        $connection = SiteIntegration::factory()->create();

        RunConnectorCollection::queueFor($connection, DateRange::thisMonth());
        RunConnectorCollection::queueFor($connection, DateRange::thisMonth());
        RunConnectorCollection::queueFor($connection, DateRange::lastMonth());

        Queue::assertPushed(RunConnectorCollection::class, 2);
        $this->assertNotNull($connection->refresh()->collection_queued_at);
    }

    public function test_the_collection_job_retries_transient_failures_with_backoff(): void
    {
        $job = new RunConnectorCollection(SiteIntegration::factory()->create(), '2026-09-01', '2026-09-30');

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
        $this->assertLessThan(55, $job->timeout);
    }

    public function test_stale_running_runs_are_closed_by_the_collector_command(): void
    {
        Queue::fake();
        $connection = SiteIntegration::factory()->create(['last_attempted_at' => now()]);
        $stale = $connection->collectorRuns()->create(['collector_key' => 'monitors', 'status' => 'running', 'started_at' => now()->subHour()]);
        $fresh = $connection->collectorRuns()->create(['collector_key' => 'monitors', 'status' => 'running', 'started_at' => now()->subMinutes(2)]);

        $this->artisan('client-reporter:collect')->assertSuccessful();

        $this->assertSame('failed', $stale->refresh()->status);
        $this->assertSame('running', $fresh->refresh()->status);
    }

    public function test_old_runs_and_superseded_renders_are_pruned(): void
    {
        Queue::fake();
        $connection = SiteIntegration::factory()->create(['last_attempted_at' => now()]);
        $connection->collectorRuns()->create(['collector_key' => 'monitors', 'status' => 'success', 'started_at' => now()->subDays(120)]);
        $connection->collectorRuns()->create(['collector_key' => 'monitors', 'status' => 'success', 'started_at' => now()->subDays(2)]);

        $report = Report::factory()->create();
        for ($i = 0; $i < 7; $i++) {
            ReportRender::query()->create([
                'report_id' => $report->id,
                'rendered_at' => now()->subDays(7 - $i),
                'data' => [],
                'branding_snapshot' => [],
                'meta' => [],
            ]);
        }

        $this->artisan('client-reporter:collect')->assertSuccessful();

        $this->assertSame(1, CollectorRun::query()->count());
        $this->assertSame(5, ReportRender::query()->where('report_id', $report->id)->count());
        $this->assertNotNull($report->refresh()->latestRender);
        $this->assertTrue(Carbon::parse($report->latestRender->rendered_at)->isSameDay(now()->subDay()));
    }

    public function test_billing_and_favicon_commands_queue_one_job_per_item(): void
    {
        Queue::fake();
        $workspace = WorkspaceIntegration::query()->create(['integration_key' => 'freeagent', 'name' => 'FreeAgent', 'status' => ConnectionStatus::Connected]);
        foreach (Client::factory()->count(2)->create() as $client) {
            ClientBillingConnection::query()->create(['client_id' => $client->id, 'workspace_integration_id' => $workspace->id, 'external_contact_id' => 'c-'.$client->id, 'external_contact_name' => $client->name]);
        }
        Site::factory()->count(3)->create(['favicon_fetched_at' => null]);

        $this->artisan('client-reporter:sync-billing')->assertSuccessful();
        $this->artisan('client-reporter:fetch-favicons')->assertSuccessful();

        Queue::assertPushed(SyncBillingConnection::class, 2);
        Queue::assertPushed(FetchSiteFavicon::class, 3);
    }

    public function test_clearing_the_queue_leaves_reserved_jobs_alone(): void
    {
        $manager = User::factory()->manager()->create();
        $payload = json_encode(['displayName' => 'App\\Jobs\\RunConnectorCollection']);
        DB::table('jobs')->insert([
            ['queue' => 'default', 'payload' => $payload, 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'default', 'payload' => $payload, 'attempts' => 1, 'reserved_at' => time(), 'available_at' => time(), 'created_at' => time()],
        ]);

        Livewire::actingAs($manager)->test(Index::class)->call('clearQueued');

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertNotNull(DB::table('jobs')->value('reserved_at'));
    }

    public function test_deleting_a_client_detaches_and_deactivates_its_portal_users(): void
    {
        $client = Client::factory()->create();
        $portalUser = User::factory()->client()->create(['client_id' => $client->id]);

        $client->delete();

        $portalUser->refresh();
        $this->assertNull($portalUser->client_id);
        $this->assertFalse($portalUser->is_active);
    }
}
