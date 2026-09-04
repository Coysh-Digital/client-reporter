<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * A compact, live queue indicator for the sidebar (à la Craft CMS): shows at a
 * glance whether background jobs are running, waiting, or idle, and links to the
 * Activity page. Polls the database queue directly.
 */
class QueueStatus extends Component
{
    public function render(): mixed
    {
        $queued = 0;
        $running = 0;
        $failed = 0;

        try {
            $queued = DB::table('jobs')->whereNull('reserved_at')->count();
            $running = DB::table('jobs')->whereNotNull('reserved_at')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // Queue tables not migrated yet — render an idle indicator.
        }

        return view('livewire.activity.queue-status', [
            'queued' => $queued,
            'running' => $running,
            'failed' => $failed,
        ]);
    }
}
