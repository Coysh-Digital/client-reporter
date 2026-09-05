<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a report's background generation currently stands. Orthogonal to the
 * report's own draft/final status: a final report can be regenerating.
 */
enum GenerationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Generating',
            self::Failed => 'Failed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'info',
            self::Failed => 'danger',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
