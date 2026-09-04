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
Schedule::command('client-reporter:collect')->hourly()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('client-reporter:check-updates')->daily();

Schedule::command('client-reporter:sync-billing')->hourly()->withoutOverlapping();
