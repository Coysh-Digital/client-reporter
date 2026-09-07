<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of a tracked background task (report generation, data
 * collection, billing sync, favicon fetch), surfaced in the sidebar activity
 * widget and the Activity page.
 */
enum BackgroundTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Done',
            self::Failed => 'Failed',
        };
    }

    /**
     * Maps to the <x-badge> variant palette.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Running => 'info',
            self::Succeeded => 'ok',
            self::Failed => 'danger',
        };
    }

    /** Whether the task is still in flight (queued or running). */
    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
