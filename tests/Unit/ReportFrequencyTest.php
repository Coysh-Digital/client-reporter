<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ReportFrequency;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Tests\TestCase;

class ReportFrequencyTest extends TestCase
{
    public function test_none_is_not_scheduled_and_has_no_period(): void
    {
        $this->assertFalse(ReportFrequency::None->isScheduled());
        $this->assertNull(ReportFrequency::None->lastCompletedPeriod());
    }

    public function test_monthly_returns_the_previous_calendar_month(): void
    {
        $period = ReportFrequency::Monthly->lastCompletedPeriod(CarbonImmutable::parse('2026-09-04'));

        $this->assertNotNull($period);
        $this->assertSame('2026-08-01', $period->start->toDateString());
        $this->assertSame('2026-08-31', $period->end->toDateString());
    }

    public function test_weekly_returns_the_previous_monday_to_sunday(): void
    {
        $now = CarbonImmutable::parse('2026-09-04');
        $period = ReportFrequency::Weekly->lastCompletedPeriod($now);

        $this->assertNotNull($period);
        $this->assertSame(1, $period->start->dayOfWeekIso, 'starts on Monday');
        $this->assertSame(7, $period->end->dayOfWeekIso, 'ends on Sunday');
        $this->assertTrue($period->end->lt($now->startOfWeek(CarbonInterface::MONDAY)), 'is a fully closed week');
    }

    public function test_quarterly_returns_a_prior_closed_period(): void
    {
        $now = CarbonImmutable::parse('2026-09-04');
        $period = ReportFrequency::Quarterly->lastCompletedPeriod($now);

        $this->assertNotNull($period);
        $this->assertTrue($period->start->lt($period->end));
        $this->assertTrue($period->end->lt($now->startOfDay()));
    }
}
