<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Billing\BillingSyncer;
use App\Models\ClientBillingConnection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pulls one client's invoices from its billing connection. Dispatched per
 * connection by `client-reporter:sync-billing`, so one slow or failing
 * accounting account never blocks the others or the scheduler tick.
 */
class SyncBillingConnection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public ClientBillingConnection $link) {}

    public function uniqueId(): string
    {
        return (string) $this->link->id;
    }

    public function handle(BillingSyncer $syncer): void
    {
        $syncer->syncOne($this->link);
    }
}
