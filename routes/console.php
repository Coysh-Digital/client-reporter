<?php

declare(strict_types=1);

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

// --memory recycles the worker before it grows too large; the short
// withoutOverlapping expiry means a worker the host OOM-kills (exit 137)
// self-heals within minutes instead of wedging the queue for a day on a
// stale lock. If 137s persist, the host is out of memory — give it more.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1 --memory=120')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('client-reporter:generate-scheduled')->daily()->withoutOverlapping();

Schedule::command('client-reporter:check-updates')->daily();

Schedule::command('client-reporter:fetch-favicons')->weekly()->withoutOverlapping();

Schedule::command('client-reporter:sync-billing')->hourly()->withoutOverlapping();
