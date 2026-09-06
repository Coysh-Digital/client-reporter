<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\CollectorRun;
use App\Models\Site;
use App\Support\SafeError;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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
    use WithPagination;

    #[Url(as: 'tab', keep: false)]
    public string $tab = 'runs';

    /** all | success | failed | running */
    #[Url(keep: false)]
    public string $status = 'all';

    #[Url(keep: false)]
    public ?int $site = null;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSite(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'success', 'failed', 'running'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('manage-integrations');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['runs', 'queued', 'failed'], true) ? $tab : 'runs';
        $this->resetPage();
    }

    public function clearQueued(): void
    {
        $this->authorize('manage-integrations');
        // Only jobs still waiting: one a worker has reserved is mid-flight and
        // would be deleted from under it.
        DB::table('jobs')->whereNull('reserved_at')->delete();
        $this->dispatch('toast', message: 'Cleared the pending queue.', type: 'ok');
    }

    public function clearFailedJobs(): void
    {
        $this->authorize('manage-integrations');
        DB::table('failed_jobs')->delete();
        $this->dispatch('toast', message: 'Cleared all failed jobs.', type: 'ok');
    }

    public function dismissFailedJob(string $uuid): void
    {
        $this->authorize('manage-integrations');
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        $this->dispatch('toast', message: 'Failed job dismissed.', type: 'ok');
    }

    public function retryFailedJob(string $uuid): void
    {
        $this->authorize('manage-integrations');

        // Only a job that is actually in failed_jobs may be retried: queue:retry
        // also accepts "all", which must not be reachable from the browser.
        if (! Str::isUuid($uuid) || ! DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            return;
        }

        // queue:retry re-dispatches the stored job onto its original connection,
        // then removes it from failed_jobs — the only correct way to retry.
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->dispatch('toast', message: 'Job re-queued for another attempt.', type: 'ok');
    }

    public function render(): mixed
    {
        return view('livewire.activity.index', [
            'runs' => $this->tab === 'runs' ? $this->runs() : null,
            'queuedJobs' => $this->tab === 'queued' ? $this->queuedJobs() : [],
            'failedJobs' => $this->tab === 'failed' ? $this->failedJobs() : [],
            'queued' => $this->count('jobs'),
            'failedJobsCount' => $this->count('failed_jobs'),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, CollectorRun>
     */
    private function runs(): LengthAwarePaginator
    {
        return CollectorRun::query()
            ->with('siteIntegration.site')
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->site !== null, fn ($q) => $q->whereHas('siteIntegration', fn ($s) => $s->where('site_id', $this->site)))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(25);
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
                    'exception' => SafeError::fromTrace((string) $job->exception),
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
