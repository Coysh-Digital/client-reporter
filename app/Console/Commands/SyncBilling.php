<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\BillingSyncer;
use Illuminate\Console\Command;

/**
 * Pulls invoices from every client's billing connection (FreeAgent, Xero)
 * into the local ledger, so reports stay current without anyone having to
 * open the accounting system.
 */
class SyncBilling extends Command
{
    protected $signature = 'client-reporter:sync-billing';

    protected $description = 'Sync invoices from connected billing integrations (FreeAgent, Xero)';

    public function handle(BillingSyncer $syncer): int
    {
        $result = $syncer->syncAll();

        $this->info("Synced {$result['synced']} invoice(s).");

        foreach ($result['failed'] as $message) {
            $this->warn($message);
        }

        return self::SUCCESS;
    }
}
