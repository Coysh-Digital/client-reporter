<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\CollectorRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * The live tiles at the top of the Activity page. Split out so polling only
 * refreshes four counts, not the whole runs list underneath.
 */
class Summary extends Component
{
    public function render(): mixed
    {
        return view('livewire.activity.summary', [
            'queued' => $this->count('jobs'),
            'running' => CollectorRun::query()->where('status', 'running')->count(),
            'failedRecently' => CollectorRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', Carbon::now()->subDay())
                ->count(),
            'failedJobsCount' => $this->count('failed_jobs'),
        ]);
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
