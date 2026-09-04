<?php

declare(strict_types=1);

namespace App\Reporting\Support;

use App\Support\Format;

/**
 * Builds the short, auto-generated "at a glance" sentence shown at the top of
 * a report block — synthesised from data the block has already resolved, never
 * hand-written. Keeps every block's summary in one consistent voice so a
 * client can skim just these lines across a whole report.
 */
class Insight
{
    /**
     * "{value} {noun}, {up/down} X% from the prior period." Falls back to
     * "{value} {noun} this period." when there is nothing to compare against.
     * Returns null when there is no current value to report at all.
     */
    public static function headline(
        string $noun,
        int|float|null $current,
        int|float|null $previous,
        string $fmt = 'number',
        ?string $currency = null,
    ): ?string {
        if ($current === null) {
            return null;
        }

        $value = Format::forType($current, $fmt, $currency);
        $change = Format::change($current, $previous);

        if ($change['percent'] === null) {
            return "{$value} {$noun} this period.";
        }

        if ($change['direction'] === 'flat') {
            return "{$value} {$noun}, unchanged from the prior period.";
        }

        $pct = Format::number(abs($change['percent']), 1);

        return "{$value} {$noun}, {$change['direction']} {$pct}% from the prior period.";
    }
}
