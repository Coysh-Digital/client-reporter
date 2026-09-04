<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\CollectorRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A live view of background data collection — what's queued, running, recently
 * finished and failed. Collection is dispatched to the database queue and
 * drained by the scheduler, so this is where staff can see it happen instead of
 * a request appearing to hang.
 */
#[Layout('components.layouts.app')]
#[Title('Activity')]
class Index extends Component
{
    #[Url(as: 'status', keep: false)]
    public string $filter = 'all';

    public function mount(): void
    {
        $this->authorize('manage-integrations');
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'running', 'success', 'failed'], true) ? $filter : 'all';
    }

    public function render(): mixed
    {
        $runs = CollectorRun::query()
            ->with('siteIntegration.site')
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return view('livewire.activity.index', [
            'runs' => $runs,
            'queuedJobs' => $this->queuedJobs(),
            'queued' => $this->queueDepth(),
            'running' => CollectorRun::query()->where('status', 'running')->count(),
            'failedRecently' => CollectorRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', Carbon::now()->subDay())
                ->count(),
            'failedJobs' => $this->failedJobCount(),
        ]);
    }

    /**
     * The jobs currently on the queue (waiting or reserved/running), read from
     * the database queue. The payload's displayName gives the job type without
     * unserialising the command.
     *
     * @return array<int, array{id: int, name: string, queue: string, attempts: int, reserved: bool, queued_at: Carbon}>
     */
    private function queuedJobs(): array
    {
        try {
            return DB::table('jobs')->orderBy('id')->limit(50)->get()
                ->map(function (object $job): array {
                    $payload = json_decode((string) $job->payload, true);
                    $name = is_array($payload) && isset($payload['displayName']) ? (string) $payload['displayName'] : 'Job';

                    return [
                        'id' => (int) $job->id,
                        'name' => Str::headline(class_basename($name)),
                        'queue' => (string) $job->queue,
                        'attempts' => (int) $job->attempts,
                        'reserved' => $job->reserved_at !== null,
                        'queued_at' => Carbon::createFromTimestamp((int) $job->created_at),
                    ];
                })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function queueDepth(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function failedJobCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
