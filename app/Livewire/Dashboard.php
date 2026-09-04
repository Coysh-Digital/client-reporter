<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Dashboard\ActivityFeed;
use App\Support\Dashboard\DashboardData;
use App\Support\DateRange;
use App\Support\UpdateChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Selected dashboard period: this_month | last_30_days. */
    public string $period = 'this_month';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['this_month', 'last_30_days'], true) ? $period : 'this_month';
    }

    public function render(DashboardData $dashboard, ActivityFeed $activity, UpdateChecker $updates): mixed
    {
        // "This month" compares against the calendar last month (the window the
        // collector warms); a rolling 30-day view compares to the prior 30 days.
        if ($this->period === 'last_30_days') {
            $range = DateRange::last30Days();
            $comparison = $range->previous();
        } else {
            $range = DateRange::thisMonth();
            $comparison = DateRange::lastMonth();
        }

        return view('livewire.dashboard', [
            'data' => $dashboard->build($range, $comparison),
            'activity' => $activity->recent(),
            'update' => auth()->user()?->isAdministrator() ? $updates->status() : ['update_available' => false],
        ]);
    }
}
