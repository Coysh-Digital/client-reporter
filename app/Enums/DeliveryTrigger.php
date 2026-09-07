<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What caused a report to be emailed: a person pressing send, or the scheduler
 * sending it automatically once a scheduled report generated.
 */
enum DeliveryTrigger: string
{
    case Manual = 'manual';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Auto => 'Auto',
        };
    }
}
