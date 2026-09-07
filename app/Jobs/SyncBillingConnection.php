<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Billing\BillingSyncer;
use App\Enums\BackgroundTaskStatus;
use App\Models\BackgroundTask;
use App\Models\ClientBillingConnection;
use App\Support\SafeError;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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

    public function displayName(): string
    {
        $this->link->loadMissing('client');

        return 'Sync billing: '.($this->link->client->name ?? 'client');
    }

    public function handle(BillingSyncer $syncer): void
    {
        $this->link->loadMissing('client');

        $task = BackgroundTask::record(
            BackgroundTask::KIND_BILLING,
            BackgroundTask::KIND_BILLING.':'.$this->link->id,
            BackgroundTaskStatus::Running,
            'Syncing billing',
            $this->link->client->name ?? 'client',
            $this->link,
        )->markRunning();

        try {
            $syncer->syncOne($this->link);
        } catch (Throwable $e) {
            $task->fail(SafeError::message($e, 'Billing sync failed.'));

            throw $e;
        }

        $task->succeed();
    }
}
