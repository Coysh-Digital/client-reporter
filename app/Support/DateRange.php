<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * An inclusive reporting period [start 00:00:00 .. end 23:59:59].
 *
 * The previous-period comparison is duration-based: the equal-length window
 * immediately preceding this one. This behaves correctly for arbitrary ranges
 * and matches calendar expectations for equal-length months (e.g. 1–31 August
 * compared with 1–31 July).
 */
readonly class DateRange
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(CarbonInterface|string $start, CarbonInterface|string $end)
    {
        $this->start = CarbonImmutable::parse($start)->startOfDay();
        $this->end = CarbonImmutable::parse($end)->endOfDay();

        if ($this->end->lessThan($this->start)) {
            throw new InvalidArgumentException('DateRange end must not be before start.');
        }
    }

    /**
     * Number of whole days covered (inclusive).
     */
    public function days(): int
    {
        return (int) round($this->start->startOfDay()->diffInDays($this->end->startOfDay())) + 1;
    }

    /**
     * The equal-length period immediately preceding this one.
     */
    public function previous(): self
    {
        $length = $this->days();
        $previousEnd = $this->start->subDay();
        $previousStart = $previousEnd->subDays($length - 1);

        return new self($previousStart, $previousEnd);
    }

    public function contains(CarbonInterface $moment): bool
    {
        return $moment->betweenIncluded($this->start, $this->end);
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ];
    }

    public function label(): string
    {
        if ($this->start->isSameDay($this->end)) {
            return $this->start->isoFormat('D MMM YYYY');
        }

        if ($this->start->year === $this->end->year) {
            if ($this->start->month === $this->end->month) {
                return $this->start->isoFormat('D').'–'.$this->end->isoFormat('D MMM YYYY');
            }

            return $this->start->isoFormat('D MMM').' – '.$this->end->isoFormat('D MMM YYYY');
        }

        return $this->start->isoFormat('D MMM YYYY').' – '.$this->end->isoFormat('D MMM YYYY');
    }

    /* ---- Presets ---- */

    public static function custom(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        return new self($start, $end);
    }

    public static function thisMonth(?CarbonInterface $now = null): self
    {
        $now = CarbonImmutable::parse($now ?? CarbonImmutable::now());

        return new self($now->startOfMonth(), $now->endOfMonth());
    }

    public static function lastMonth(?CarbonInterface $now = null): self
    {
        $ref = CarbonImmutable::parse($now ?? CarbonImmutable::now())->subMonthNoOverflow();

        return new self($ref->startOfMonth(), $ref->endOfMonth());
    }

    public static function lastWeek(?CarbonInterface $now = null): self
    {
        $now = CarbonImmutable::parse($now ?? CarbonImmutable::now());

        return new self($now->subDays(7), $now->subDay());
    }

    public static function last7Days(?CarbonInterface $now = null): self
    {
        return self::lastWeek($now);
    }

    public static function last30Days(?CarbonInterface $now = null): self
    {
        $now = CarbonImmutable::parse($now ?? CarbonImmutable::now());

        return new self($now->subDays(30), $now->subDay());
    }

    public static function thisQuarter(?CarbonInterface $now = null): self
    {
        $now = CarbonImmutable::parse($now ?? CarbonImmutable::now());

        return new self($now->firstOfQuarter(), $now->lastOfQuarter());
    }

    public static function lastQuarter(?CarbonInterface $now = null): self
    {
        $ref = CarbonImmutable::parse($now ?? CarbonImmutable::now())->subQuarterNoOverflow();

        return new self($ref->firstOfQuarter(), $ref->lastOfQuarter());
    }

    /**
     * Build a range from a preset key. "custom" requires explicit dates.
     */
    public static function fromPreset(string $preset, ?CarbonInterface $now = null): self
    {
        return match ($preset) {
            'last_week', 'last_7_days' => self::lastWeek($now),
            'last_30_days' => self::last30Days($now),
            'this_month' => self::thisMonth($now),
            'last_month' => self::lastMonth($now),
            'this_quarter' => self::thisQuarter($now),
            'last_quarter' => self::lastQuarter($now),
            default => throw new InvalidArgumentException("Unknown date range preset [{$preset}]."),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'last_week' => 'Last week',
            'last_30_days' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'last_quarter' => 'Last quarter',
            'custom' => 'Custom range',
        ];
    }
}
