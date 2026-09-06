<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Dashboard\ActivityFeed;
use App\Support\Dashboard\DashboardData;
use App\Support\DateRange;
use App\Support\UpdateChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public const PERIODS = ['this_month' => 'This month', 'last_30_days' => 'Last 30 days', 'last_90_days' => 'Last 90 days'];

    /** Selected dashboard period; kept in the URL so a refresh or shared link lands on the same view. */
    #[Url(keep: false)]
    public string $period = 'this_month';

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : 'this_month';
    }

    public function render(DashboardData $dashboard, ActivityFeed $activity, UpdateChecker $updates): mixed
    {
        // "This month" compares against the calendar last month (the window the
        // collector warms); rolling views compare to the prior window of the
        // same length.
        [$range, $comparison] = match ($this->period) {
            'last_30_days' => [$r = DateRange::last30Days(), $r->previous()],
            'last_90_days' => [$r = DateRange::last90Days(), $r->previous()],
            default => [DateRange::thisMonth(), DateRange::lastMonth()],
        };

        return view('livewire.dashboard', [
            'data' => $dashboard->build($range, $comparison),
            'activity' => $activity->recent(),
            'update' => auth()->user()?->isAdministrator() ? $updates->status() : ['update_available' => false],
        ]);
    }
}
