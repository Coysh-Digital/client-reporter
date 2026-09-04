<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\CollectorRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A live view of background work: what's on the queue right now, the history of
 * collection runs, and any failed queue jobs (which can be retried or dismissed
 * once reviewed). Collection is dispatched to the database queue and drained by
 * the scheduler, so this is where staff can see it happen.
 */
#[Layout('components.layouts.app')]
#[Title('Activity')]
class Index extends Component
{
    #[Url(as: 'tab', keep: false)]
    public string $tab = 'runs';

    public function mount(): void
    {
        $this->authorize('manage-integrations');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['runs', 'queued', 'failed'], true) ? $tab : 'runs';
    }

    public function clearQueued(): void
    {
        $this->authorize('manage-integrations');
        DB::table('jobs')->delete();
        session()->flash('status', 'Cleared the pending queue.');
    }

    public function clearFailedJobs(): void
    {
        $this->authorize('manage-integrations');
        DB::table('failed_jobs')->delete();
        session()->flash('status', 'Cleared all failed jobs.');
    }

    public function dismissFailedJob(string $uuid): void
    {
        $this->authorize('manage-integrations');
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        session()->flash('status', 'Failed job dismissed.');
    }

    public function retryFailedJob(string $uuid): void
    {
        $this->authorize('manage-integrations');
        // queue:retry re-dispatches the stored job onto its original connection,
        // then removes it from failed_jobs — the only correct way to retry.
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        session()->flash('status', 'Job re-queued for another attempt.');
    }

    public function render(): mixed
    {
        return view('livewire.activity.index', [
            'runs' => $this->tab === 'runs' ? $this->runs() : collect(),
            'queuedJobs' => $this->tab === 'queued' ? $this->queuedJobs() : [],
            'failedJobs' => $this->tab === 'failed' ? $this->failedJobs() : [],
            'queued' => $this->count('jobs'),
            'running' => CollectorRun::query()->where('status', 'running')->count(),
            'failedRecently' => CollectorRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', Carbon::now()->subDay())
                ->count(),
            'failedJobsCount' => $this->count('failed_jobs'),
        ]);
    }

    /**
     * @return Collection<int, CollectorRun>
     */
    private function runs(): Collection
    {
        return CollectorRun::query()
            ->with('siteIntegration.site')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();
    }

    /**
     * Jobs currently on the queue (waiting or reserved/running). The payload's
     * displayName gives the job type without unserialising the command.
     *
     * @return array<int, array{id: int, name: string, reserved: bool, attempts: int, queued_at: Carbon}>
     */
    private function queuedJobs(): array
    {
        try {
            return DB::table('jobs')->orderBy('id')->limit(100)->get()
                ->map(fn (object $job): array => [
                    'id' => (int) $job->id,
                    'name' => $this->jobName($job->payload),
                    'reserved' => $job->reserved_at !== null,
                    'attempts' => (int) $job->attempts,
                    'queued_at' => Carbon::createFromTimestamp((int) $job->created_at),
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Failed queue jobs, newest first.
     *
     * @return array<int, array{uuid: string, name: string, queue: string, failed_at: Carbon, exception: string}>
     */
    private function failedJobs(): array
    {
        try {
            return DB::table('failed_jobs')->orderByDesc('id')->limit(50)->get()
                ->map(fn (object $job): array => [
                    'uuid' => (string) $job->uuid,
                    'name' => $this->jobName($job->payload),
                    'queue' => (string) $job->queue,
                    'failed_at' => Carbon::parse((string) $job->failed_at),
                    'exception' => Str::of((string) $job->exception)->explode("\n")->first() ?? '',
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function jobName(?string $payload): string
    {
        $decoded = json_decode((string) $payload, true);
        $name = is_array($decoded) && isset($decoded['displayName']) ? (string) $decoded['displayName'] : 'Job';

        return Str::headline(class_basename($name));
    }

    private function count(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
