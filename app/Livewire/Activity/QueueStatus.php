<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Enums\BackgroundTaskStatus;
use App\Models\BackgroundTask;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * A compact, live activity indicator for the sidebar: shows what background work
 * is running or queued right now — named, with progress where known — and links
 * to the Activity page. Polls the background-tasks table.
 */
class QueueStatus extends Component
{
    /** Active tasks not touched within this window are treated as stale and hidden. */
    private const STALE_MINUTES = 20;

    public function render(): mixed
    {
        $tasks = collect();
        $running = 0;
        $queued = 0;
        $failed = 0;

        try {
            $fresh = now()->subMinutes(self::STALE_MINUTES);
            $running = BackgroundTask::query()->where('status', BackgroundTaskStatus::Running->value)->where('updated_at', '>=', $fresh)->count();
            $queued = BackgroundTask::query()->where('status', BackgroundTaskStatus::Queued->value)->where('updated_at', '>=', $fresh)->count();

            $tasks = BackgroundTask::query()
                ->active()
                ->where('updated_at', '>=', $fresh)
                ->orderByRaw("case when status = '".BackgroundTaskStatus::Running->value."' then 0 else 1 end")
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // Tables not migrated yet — render an idle indicator.
        }

        return view('livewire.activity.queue-status', [
            'tasks' => $tasks,
            'running' => $running,
            'queued' => $queued,
            'failed' => $failed,
        ]);
    }
}
