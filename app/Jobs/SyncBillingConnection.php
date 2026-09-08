<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Billing\BillingSyncer;
use App\Enums\BackgroundTaskStatus;
use App\Integrations\Support\AuthenticationException;
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
        } catch (AuthenticationException $e) {
            // A rejected credential won't recover on retry, and the syncer has
            // already disabled the connection and alerted staff. Record it and
            // stop — don't re-throw, or the queue would retry (and Laravel would
            // re-log) the same dead-credential error several times over.
            $task->fail(SafeError::message($e, 'Billing sync failed.'));

            return;
        } catch (Throwable $e) {
            // Soft/transient failure: record it and re-throw so the queue's
            // retry-then-disable grace still applies.
            $task->fail(SafeError::message($e, 'Billing sync failed.'));

            throw $e;
        }

        $task->succeed();
    }
}
