<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\CollectorRunner;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Collects data for one connection over one period. Dispatched by the
 * `client-reporter:collect` command; safe to run on the database queue so a
 * single cron entry running the scheduler operates the whole app on shared
 * hosting.
 */
class RunConnectorCollection implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public SiteIntegration $connection,
        public string $periodStart,
        public string $periodEnd,
    ) {}

    public function handle(CollectorRunner $runner): void
    {
        $runner->collectAll($this->connection, new DateRange($this->periodStart, $this->periodEnd));
    }
}
