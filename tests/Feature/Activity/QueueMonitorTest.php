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

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

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

    private function failedJob(string $uuid): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\RunConnectorCollection']),
            'exception' => "RuntimeException: something broke\n#0 ...",
            'failed_at' => now(),
        ]);
    }

    public function test_queued_tab_lists_queued_jobs(): void
    {
        $this->queueJob();

        $jobs = Livewire::actingAs($this->manager())->test(Index::class)
            ->set('tab', 'queued')
            ->viewData('queuedJobs');

        $this->assertCount(1, $jobs);
        $this->assertSame('Run Connector Collection', $jobs[0]['name']);
        $this->assertFalse($jobs[0]['reserved']);
    }

    public function test_queued_jobs_can_be_cleared(): void
    {
        $this->queueJob();
        $this->queueJob();

        Livewire::actingAs($this->manager())->test(Index::class)->call('clearQueued');

        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_failed_jobs_are_listed_and_can_be_dismissed_or_cleared(): void
    {
        $this->failedJob('uuid-1');
        $this->failedJob('uuid-2');

        $component = Livewire::actingAs($this->manager())->test(Index::class)->set('tab', 'failed');
        $this->assertCount(2, $component->viewData('failedJobs'));

        $component->call('dismissFailedJob', 'uuid-1');
        $this->assertNull(DB::table('failed_jobs')->where('uuid', 'uuid-1')->first());
        $this->assertNotNull(DB::table('failed_jobs')->where('uuid', 'uuid-2')->first());

        $component->call('clearFailedJobs');
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_sidebar_queue_status_reflects_the_queue(): void
    {
        Livewire::actingAs($this->manager())->test(QueueStatus::class)->assertSee('Idle');

        $this->queueJob();
        Livewire::actingAs($this->manager())->test(QueueStatus::class)->assertSee('queued');

        $this->queueJob(reserved: true);
        Livewire::actingAs($this->manager())->test(QueueStatus::class)->assertSee('running');
    }
}
