<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\BillingSyncer;
use App\Jobs\SyncBillingConnection;
use App\Models\ClientBillingConnection;
use Illuminate\Console\Command;

/**
 * Pulls invoices from every client's billing connection (FreeAgent, Xero)
 * into the local ledger, so reports stay current without anyone having to
 * open the accounting system. Each connection is queued as its own job.
 */
class SyncBilling extends Command
{
    protected $signature = 'client-reporter:sync-billing {--sync : Sync immediately instead of queueing}';

    protected $description = 'Sync invoices from connected billing integrations (FreeAgent, Xero)';

    public function handle(BillingSyncer $syncer): int
    {
        if ($this->option('sync')) {
            $result = $syncer->syncAll();

            $this->info("Synced {$result['synced']} invoice(s).");

            foreach ($result['failed'] as $message) {
                $this->warn($message);
            }

            return self::SUCCESS;
        }

        // Skip links auto-disabled after repeated failures — matching syncAll —
        // so a dead credential isn't re-queued (and re-erroring) every hour.
        $links = ClientBillingConnection::query()->whereNull('disabled_at')->get();

        foreach ($links as $link) {
            SyncBillingConnection::dispatch($link);
        }

        $this->info("Queued billing sync for {$links->count()} client connection(s).");

        return self::SUCCESS;
    }
}
