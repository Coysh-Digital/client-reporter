<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectionSchedule;
use App\Models\CollectorRun;
use App\Models\SiteIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * One vocabulary for "how is this connection doing", derived from the stored
 * status, the most recent collector run and the collection schedule. The site
 * page, catalog and dashboard all speak this rather than re-deriving it.
 */
final readonly class ConnectionState
{
    /** A queued collection older than this is assumed lost; the row stops saying "Syncing". */
    public const QUEUED_STALE_MINUTES = 30;

    /** A run still "running" after this is treated as abandoned. */
    public const RUNNING_STALE_MINUTES = 15;

    private function __construct(
        public string $label,
        public string $variant,
        public bool $syncing,
        public ?string $detail,
        public ?Carbon $lastCollectedAt,
        public ?CarbonImmutable $nextDueAt,
        public string $action,
    ) {}

    public static function from(SiteIntegration $connection, CollectionSchedule $schedule): self
    {
        $latestRun = $connection->relationLoaded('latestRun') ? $connection->latestRun : null;
        $syncing = self::isSyncing($connection, $latestRun);

        [$label, $variant, $action] = match (true) {
            $syncing => ['Syncing', 'info', 'wait'],
            $connection->status === ConnectionStatus::Disabled => ['Disabled after repeated failures', 'danger', 'reconnect'],
            $connection->status === ConnectionStatus::AuthExpired => ['Authentication expired', 'danger', 'reconnect'],
            $connection->status === ConnectionStatus::Error => ['Attention required', 'danger', 'retry'],
            $connection->status === ConnectionStatus::NeedsAttention && $connection->last_failure_kind === 'rate_limit' => ['Rate limited', 'warn', 'retry'],
            $connection->status === ConnectionStatus::NeedsAttention => ['Last sync failed', 'warn', 'retry'],
            $connection->status === ConnectionStatus::Connected => ['Connected', 'ok', 'collect'],
            default => ['Not connected', 'neutral', 'connect'],
        };

        $detail = $connection->status->needsAttention() && ! $syncing ? $connection->last_error : null;

        return new self(
            label: $label,
            variant: $variant,
            syncing: $syncing,
            detail: $detail !== '' ? $detail : null,
            lastCollectedAt: $connection->last_collected_at,
            nextDueAt: $connection->status->isLive() ? $schedule->nextDueAt($connection) : null,
            action: $action,
        );
    }

    /**
     * Whether a collection is in flight: recently queued and not yet attempted,
     * or a run that started recently and has not finished.
     */
    private static function isSyncing(SiteIntegration $connection, ?CollectorRun $latestRun): bool
    {
        $queuedAt = $connection->collection_queued_at;
        if ($queuedAt !== null
            && $queuedAt->gt(now()->subMinutes(self::QUEUED_STALE_MINUTES))
            && ($connection->last_attempted_at === null || $connection->last_attempted_at->lt($queuedAt))) {
            return true;
        }

        return $latestRun !== null
            && $latestRun->status === 'running'
            && $latestRun->started_at !== null
            && $latestRun->started_at->gt(now()->subMinutes(self::RUNNING_STALE_MINUTES));
    }

    /**
     * "Last collected 2 hours ago · Next due in 4 hours", or what is known.
     */
    public function timing(): string
    {
        $parts = [];

        if ($this->lastCollectedAt !== null) {
            $parts[] = 'Last collected '.$this->lastCollectedAt->diffForHumans();
        } else {
            $parts[] = 'Never collected';
        }

        if ($this->syncing) {
            $parts[] = 'collecting now';
        } elseif ($this->nextDueAt !== null) {
            $parts[] = $this->nextDueAt->isPast() ? 'due now' : 'next due '.$this->nextDueAt->diffForHumans();
        }

        return implode(' · ', $parts);
    }
}
