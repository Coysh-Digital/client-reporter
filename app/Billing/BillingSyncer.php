<?php

declare(strict_types=1);

namespace App\Billing;

use App\Integrations\Support\AuthenticationException;
use App\Integrations\Support\IntegrationException;
use App\Models\ClientBillingConnection;
use App\Support\IntegrationAlerts;
use App\Support\SafeError;
use Throwable;

/**
 * Pulls every client-mapped billing connection's invoices (FreeAgent, Xero)
 * into the local invoice ledger. A failure on one client's connection never
 * stops the rest — each is synced independently and reported separately.
 *
 * A connection that keeps failing is auto-disabled after the configured number
 * of consecutive failures and then skipped, so a dead credential isn't retried
 * (and re-logged) every hour until someone reconnects it.
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

        $links = ClientBillingConnection::query()
            ->whereNull('disabled_at')
            ->with(['client', 'workspaceIntegration'])
            ->get();

        foreach ($links as $link) {
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

        try {
            $count = $integration->syncInvoices($link);
        } catch (Throwable $e) {
            $this->recordFailure($link, $e);

            throw $e;
        }

        $link->update([
            'last_synced_at' => now(),
            'last_attempted_at' => now(),
            'consecutive_failures' => 0,
            'last_error' => null,
            'disabled_at' => null,
        ]);

        return $count;
    }

    /**
     * Record a failed sync and disable the link once it has failed too many
     * times in a row.
     */
    private function recordFailure(ClientBillingConnection $link, Throwable $e): void
    {
        $failures = $link->consecutive_failures + 1;

        $update = [
            'consecutive_failures' => $failures,
            'last_attempted_at' => now(),
            'last_error' => SafeError::message($e, 'The billing connection could not be synced.'),
        ];

        // A rejected credential won't recover until it's reconnected, so disable
        // it right away rather than retrying (and re-logging) it every hour. Soft
        // or transient failures get the usual retry-then-disable grace.
        $wasDisabled = $link->disabled_at !== null;
        $disableNow = $e instanceof AuthenticationException || $failures >= $this->failureThreshold();
        if ($disableNow) {
            $update['disabled_at'] = now();
        }

        $link->update($update);

        // Alert staff the first time it disables (not on every hourly retry).
        if (! $wasDisabled && $disableNow) {
            app(IntegrationAlerts::class)->billingNeedsAction(
                $link,
                $e instanceof AuthenticationException
                    ? 'The billing connection was rejected — reconnect it to resume syncing.'
                    : 'The billing connection was disabled after repeated sync failures.',
            );
        }
    }

    private function failureThreshold(): int
    {
        return max(1, (int) config('client-reporter.collection.failure_threshold', 5));
    }
}
