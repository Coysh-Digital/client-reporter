<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One queue-worker slot. The scheduler runs several of these in parallel (as
 * many as the "Parallel jobs" setting allows), each with its own no-overlap
 * lock, so jobs process concurrently on the database queue. Wrapping
 * `queue:work` in a per-slot command is what gives each worker a distinct lock
 * (identical scheduled commands would share one and never run side by side).
 */
class WorkQueue extends Command
{
    protected $signature = 'client-reporter:work {slot=0 : Which worker slot this is}';

    protected $description = 'Drain the database queue for one worker slot (used by the scheduler for parallel processing)';

    public function handle(): int
    {
        return $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 55,
            '--tries' => 1,
            '--memory' => 120,
        ]);
    }
}
