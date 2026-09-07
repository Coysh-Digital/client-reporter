<?php

declare(strict_types=1);

use App\Support\Settings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Scheduled work. A single cron entry running `php artisan schedule:run` every
| minute operates the whole application — no persistent worker is required.
|
|   * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
|
| `collect` queues due collections; the queue:work line drains the database
| queue each minute so shared hosts need nothing else. VPS users running a
| persistent worker (or Horizon) can remove the queue:work line.
*/
// Current month on the regular cadence (the command's own interval decides
// which connections are actually due each run).
Schedule::command('client-reporter:collect')->hourly()->withoutOverlapping();

// The previous month is a completed, stable period — refresh it once a day
// rather than re-collecting it every cycle alongside the current month.
Schedule::command('client-reporter:collect --history')->dailyAt('04:00')->withoutOverlapping();

// Drain the database queue each minute. The "Parallel jobs" setting decides how
// many worker slots run at once (default 1 = jobs one at a time); each slot is
// a separate command with its own no-overlap lock, so they process jobs in
// parallel and a worker the host OOM-kills (exit 137) self-heals within minutes
// instead of wedging the queue on a stale lock. `queue:work --memory` inside the
// wrapper recycles a worker before it grows too large.
$queueWorkers = 1;
try {
    $maxWorkers = (int) config('client-reporter.queue.max_workers', 50);
    $queueWorkers = max(1, min($maxWorkers, (int) app(Settings::class)->get('queue_workers', config('client-reporter.queue.workers', 1))));
} catch (Throwable) {
    // Settings table not available yet (fresh install) — fall back to one worker.
}

for ($slot = 0; $slot < $queueWorkers; $slot++) {
    Schedule::command('client-reporter:work '.$slot)
        ->everyMinute()
        ->withoutOverlapping(5);
}

Schedule::command('client-reporter:generate-scheduled')->daily()->withoutOverlapping();

Schedule::command('client-reporter:check-updates')->daily()->withoutOverlapping();

Schedule::command('client-reporter:fetch-favicons')->weekly()->withoutOverlapping();

Schedule::command('client-reporter:sync-billing')->hourly()->withoutOverlapping();
