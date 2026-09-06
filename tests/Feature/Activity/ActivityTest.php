<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Enums\ConnectionStatus;
use App\Jobs\RunConnectorCollection;
use App\Livewire\Activity\Index;
use App\Livewire\Integrations\SitePanel;
use App\Models\CollectorRun;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_page_lists_collection_runs(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['name' => 'Northwind']);
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);
        CollectorRun::query()->create([
            'site_integration_id' => $connection->id,
            'collector_key' => 'ga4.traffic',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 4200,
            'records_written' => 0,
            'error_message' => 'invalid_grant',
        ]);

        Livewire::actingAs($manager)->test(Index::class)
            ->assertSee('Northwind')
            ->assertSee('ga4.traffic')
            ->assertSee('invalid_grant')
            ->assertSee('Failed');
    }

    public function test_activity_page_is_gated_to_managers(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->get(route('activity.index'))->assertForbidden();

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('activity.index'))->assertOk();
    }

    public function test_collect_now_queues_a_background_job_instead_of_blocking(): void
    {
        Queue::fake();

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create([
            'site_id' => $site->id,
            'status' => ConnectionStatus::Connected,
        ]);

        Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->call('collectNow', $connection->id);

        Queue::assertPushed(RunConnectorCollection::class, fn ($job) => $job->siteIntegration->is($connection));
    }

    public function test_runs_can_be_filtered_by_outcome_and_site_and_are_paginated(): void
    {
        $manager = User::factory()->manager()->create();
        $alpha = Site::factory()->create(['name' => 'Alpha Site']);
        $beta = Site::factory()->create(['name' => 'Beta Site']);
        $alphaConnection = SiteIntegration::factory()->create(['site_id' => $alpha->id]);
        $betaConnection = SiteIntegration::factory()->create(['site_id' => $beta->id]);

        foreach (range(1, 30) as $i) {
            CollectorRun::query()->create([
                'site_integration_id' => $alphaConnection->id,
                'collector_key' => 'monitors',
                'status' => 'success',
                'started_at' => now()->subMinutes($i),
                'finished_at' => now()->subMinutes($i),
                'records_written' => 1,
            ]);
        }
        CollectorRun::query()->create([
            'site_integration_id' => $betaConnection->id,
            'collector_key' => 'summary',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'quota exceeded',
        ]);

        Livewire::actingAs($manager)->test(Index::class)
            ->assertSee('Beta Site')
            ->assertSeeHtml('aria-label="Pagination"')
            ->call('setStatus', 'failed')
            ->assertSee('quota exceeded')
            ->assertDontSee('monitors')
            ->call('setStatus', 'all')
            ->set('site', $alpha->id)
            ->assertSee('monitors')
            ->assertDontSee('quota exceeded')
            ->call('setStatus', 'bogus')
            ->assertSet('status', 'all');
    }
}
