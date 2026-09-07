<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Models\SiteIntegration;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * When a connection is due for collection. Keyed off the last *attempt*
 * (falling back to the last success for rows predating that column), so a
 * connection that keeps failing backs off to the normal interval instead of
 * being retried on every scheduler tick.
 */
final class CollectionSchedule
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * The interval for a connection: its own override, else its workspace
     * connection's, else the global setting. Passing null gives the global
     * interval (used where no specific connection is in scope).
     */
    public function intervalMinutes(?SiteIntegration $connection = null): int
    {
        return $this->connectionOverride($connection) ?? $this->globalInterval();
    }

    private function globalInterval(): int
    {
        return (int) $this->settings->get(
            'collection_interval',
            config('client-reporter.collection.default_interval', 360),
        );
    }

    /**
     * A per-connection interval override, resolved site → workspace, or null
     * when neither sets one.
     */
    private function connectionOverride(?SiteIntegration $connection): ?int
    {
        if ($connection === null) {
            return null;
        }

        $own = $connection->setting('collection_interval');
        if (is_numeric($own) && (int) $own > 0) {
            return (int) $own;
        }

        if ($connection->usesWorkspace()) {
            $connection->loadMissing('workspaceIntegration');
            $workspace = $connection->workspaceIntegration?->setting('collection_interval');
            if (is_numeric($workspace) && (int) $workspace > 0) {
                return (int) $workspace;
            }
        }

        return null;
    }

    /**
     * When the next scheduled collection falls, or null when the connection
     * has never been attempted (i.e. it is due now).
     */
    public function nextDueAt(SiteIntegration $connection): ?CarbonImmutable
    {
        $last = $connection->last_attempted_at ?? $connection->last_collected_at;

        return $last !== null ? $last->toImmutable()->addMinutes($this->intervalMinutes($connection)) : null;
    }

    public function isDue(SiteIntegration $connection): bool
    {
        $next = $this->nextDueAt($connection);

        return $next === null || $next->isPast();
    }
}
