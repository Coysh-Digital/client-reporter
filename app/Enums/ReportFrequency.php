<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How often a site's report is generated on a schedule. `None` (the default)
 * means the site is not on a schedule at all — reports are only ever made by
 * hand. The other cases drive the `client-reporter:generate-scheduled` command,
 * which auto-generates a report once each period has fully closed.
 */
enum ReportFrequency: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Not scheduled',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
        };
    }

    public function isScheduled(): bool
    {
        return $this !== self::None;
    }

    /**
     * The most recent period of this frequency that has fully finished as of
     * $now. These are discrete, non-overlapping calendar periods so a report
     * for a period can be de-duplicated by its exact start/end dates. Returns
     * null for `None`.
     */
    public function lastCompletedPeriod(?CarbonInterface $now = null): ?DateRange
    {
        $now = CarbonImmutable::parse($now ?? CarbonImmutable::now());

        return match ($this) {
            self::None => null,
            self::Weekly => new DateRange(
                $start = $now->startOfWeek(CarbonInterface::MONDAY)->subWeek(),
                $start->endOfWeek(CarbonInterface::SUNDAY),
            ),
            self::Monthly => DateRange::lastMonth($now),
            self::Quarterly => DateRange::lastQuarter($now),
        };
    }

    /**
     * Options for a schedule <select>, value => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
