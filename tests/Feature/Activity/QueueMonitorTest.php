<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Livewire\Activity\Index;
use App\Livewire\Activity\QueueStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function queueJob(bool $reserved = false, string $class = 'App\\Jobs\\RunConnectorCollection'): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => $class, 'job' => 'x', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => $reserved ? now()->timestamp : null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
    }

    public function test_activity_page_lists_queued_jobs(): void
    {
        $this->queueJob();

        $jobs = Livewire::actingAs(User::factory()->manager()->create())->test(Index::class)->viewData('queuedJobs');

        $this->assertCount(1, $jobs);
        $this->assertSame('Run Connector Collection', $jobs[0]['name']);
        $this->assertFalse($jobs[0]['reserved']);
    }

    public function test_sidebar_queue_status_reflects_the_queue(): void
    {
        Livewire::actingAs(User::factory()->manager()->create())->test(QueueStatus::class)->assertSee('Idle');

        $this->queueJob();
        Livewire::actingAs(User::factory()->manager()->create())->test(QueueStatus::class)->assertSee('queued');

        $this->queueJob(reserved: true);
        Livewire::actingAs(User::factory()->manager()->create())->test(QueueStatus::class)->assertSee('running');
    }
}
