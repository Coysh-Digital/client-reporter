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

    public function intervalMinutes(): int
    {
        return (int) $this->settings->get(
            'collection_interval',
            config('client-reporter.collection.default_interval', 360),
        );
    }

    /**
     * When the next scheduled collection falls, or null when the connection
     * has never been attempted (i.e. it is due now).
     */
    public function nextDueAt(SiteIntegration $connection): ?CarbonImmutable
    {
        $last = $connection->last_attempted_at ?? $connection->last_collected_at;

        return $last !== null ? $last->toImmutable()->addMinutes($this->intervalMinutes()) : null;
    }

    public function isDue(SiteIntegration $connection): bool
    {
        $next = $this->nextDueAt($connection);

        return $next === null || $next->isPast();
    }
}
