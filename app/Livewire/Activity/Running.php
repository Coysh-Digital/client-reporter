<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Enums\BackgroundTaskStatus;
use App\Models\BackgroundTask;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The "Currently running" panel on the Activity page: a live list of the
 * background tasks in flight right now — named, with progress where known —
 * separate from the paginated run history so polling refreshes only this.
 */
class Running extends Component
{
    private const STALE_MINUTES = 20;

    public function render(): mixed
    {
        $tasks = new Collection;

        try {
            $tasks = BackgroundTask::query()
                ->active()
                ->where('updated_at', '>=', now()->subMinutes(self::STALE_MINUTES))
                ->orderByRaw("case when status = '".BackgroundTaskStatus::Running->value."' then 0 else 1 end")
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get();
        } catch (\Throwable) {
            // Table not migrated yet — render nothing.
        }

        return view('livewire.activity.running', ['tasks' => $tasks]);
    }
}
