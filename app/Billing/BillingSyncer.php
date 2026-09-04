<?php

declare(strict_types=1);

namespace App\Billing;

use App\Integrations\Support\IntegrationException;
use App\Models\ClientBillingConnection;

/**
 * Pulls every client-mapped billing connection's invoices (FreeAgent, Xero)
 * into the local invoice ledger. A failure on one client's connection never
 * stops the rest — each is synced independently and reported separately.
 */
class BillingSyncer
{
    /**
     * @return array{synced: int, failed: array<int, string>}
     */
    public function syncAll(): array
    {
        $synced = 0;
        $failed = [];

        foreach (ClientBillingConnection::query()->with(['client', 'workspaceIntegration'])->get() as $link) {
            try {
                $synced += $this->syncOne($link);
            } catch (IntegrationException $e) {
                $failed[] = $link->client->name.': '.$e->getMessage();
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * @throws IntegrationException
     */
    public function syncOne(ClientBillingConnection $link): int
    {
        $integration = $link->workspaceIntegration?->integration();
        if ($integration === null) {
            return 0;
        }

        $count = $integration->syncInvoices($link);
        $link->update(['last_synced_at' => now()]);

        return $count;
    }
}
