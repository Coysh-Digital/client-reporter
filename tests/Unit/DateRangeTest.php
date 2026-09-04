<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_inclusive_day_count(): void
    {
        $range = new DateRange('2026-08-01', '2026-08-31');

        $this->assertSame(31, $range->days());
    }

    public function test_single_day_range(): void
    {
        $range = new DateRange('2026-08-15', '2026-08-15');

        $this->assertSame(1, $range->days());
    }

    public function test_previous_period_of_august_is_july(): void
    {
        $august = new DateRange('2026-08-01', '2026-08-31');
        $previous = $august->previous();

        $this->assertSame('2026-07-01', $previous->start->toDateString());
        $this->assertSame('2026-07-31', $previous->end->toDateString());
        $this->assertSame(31, $previous->days());
    }

    public function test_previous_period_of_arbitrary_range_is_equal_length_and_adjacent(): void
    {
        $range = new DateRange('2026-08-10', '2026-08-16'); // 7 days
        $previous = $range->previous();

        $this->assertSame(7, $previous->days());
        $this->assertSame('2026-08-03', $previous->start->toDateString());
        $this->assertSame('2026-08-09', $previous->end->toDateString());
    }

    public function test_last_month_preset(): void
    {
        $now = CarbonImmutable::create(2026, 9, 15);
        $range = DateRange::lastMonth($now);

        $this->assertSame('2026-08-01', $range->start->toDateString());
        $this->assertSame('2026-08-31', $range->end->toDateString());
    }

    public function test_this_quarter_preset(): void
    {
        $now = CarbonImmutable::create(2026, 8, 20);
        $range = DateRange::thisQuarter($now);

        $this->assertSame('2026-07-01', $range->start->toDateString());
        $this->assertSame('2026-09-30', $range->end->toDateString());
    }

    public function test_from_preset_resolves_known_keys(): void
    {
        $range = DateRange::fromPreset('last_month', CarbonImmutable::create(2026, 3, 10));

        $this->assertSame('2026-02-01', $range->start->toDateString());
        $this->assertSame('2026-02-28', $range->end->toDateString());
    }

    public function test_label_for_same_month_range(): void
    {
        $range = new DateRange('2026-08-01', '2026-08-31');

        $this->assertSame('1–31 Aug 2026', $range->label());
    }
}
