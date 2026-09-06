<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\CollectorRunner;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Collects data for one connection over one period. Dispatched by the
 * `client-reporter:collect` command and "Collect now"; safe to run on the
 * database queue so a single cron entry running the scheduler operates the
 * whole app on shared hosting.
 *
 * Unique per connection and period: if the queue backs up, the next hourly
 * tick does not stack a second copy of the same collection. A transient
 * provider error (network blip, 5xx) is retried with backoff; the runner
 * itself records per-collector failures, so a retry only happens when the
 * job as a whole blew up.
 */
class RunConnectorCollection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /** Under the scheduler worker's 55-second window, so a stuck provider cannot wedge it. */
    public int $timeout = 50;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(
        public SiteIntegration $siteIntegration,
        public string $periodStart,
        public string $periodEnd,
    ) {}

    public function uniqueId(): string
    {
        return $this->siteIntegration->id.'|'.$this->periodStart;
    }

    /**
     * Dispatch a collection and mark the connection so the UI can show it as
     * queued before a worker picks it up.
     */
    public static function queueFor(SiteIntegration $connection, DateRange $range): void
    {
        $connection->forceFill(['collection_queued_at' => now()])->save();

        self::dispatch($connection, $range->start->toDateString(), $range->end->toDateString());
    }

    public function handle(CollectorRunner $runner): void
    {
        $runner->collectAll($this->siteIntegration, new DateRange($this->periodStart, $this->periodEnd));
    }
}
