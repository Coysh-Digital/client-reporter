<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\ReportPeriodStatus;
use App\Models\Report;
use App\Models\Site;
use App\Support\DateRange;
use Illuminate\Support\Collection;

/**
 * Works out where each site's report stands for a period. A report is "sent"
 * once it has a share link / email (a ReportShare); generated-but-unshared is
 * "ready"; un-generated is "draft"; and a site with no overlapping report is
 * "not started".
 */
class ReportStatusResolver
{
    /**
     * @param  Collection<int, Site>  $sites
     * @return array<int, array{status: ReportPeriodStatus, report: ?Report}> keyed by site id
     */
    public function forSites(Collection $sites, ?DateRange $period = null): array
    {
        $period ??= DateRange::thisMonth();
        $siteIds = $sites->pluck('id')->all();

        $out = [];
        foreach ($siteIds as $id) {
            $out[$id] = ['status' => ReportPeriodStatus::NotStarted, 'report' => null];
        }

        if ($siteIds === []) {
            return $out;
        }

        // Latest report per site whose range overlaps the period.
        $reports = Report::query()
            ->whereIn('site_id', $siteIds)
            ->whereDate('range_start', '<=', $period->end->toDateString())
            ->whereDate('range_end', '>=', $period->start->toDateString())
            ->withCount('shares')
            ->orderByDesc('range_start')
            ->orderByDesc('id')
            ->get();

        foreach ($reports as $report) {
            // First (latest) report seen for a site wins.
            if ($out[$report->site_id]['report'] !== null) {
                continue;
            }

            $out[$report->site_id] = [
                'status' => $this->statusFor($report),
                'report' => $report,
            ];
        }

        return $out;
    }

    private function statusFor(Report $report): ReportPeriodStatus
    {
        if (! $report->isGenerated() && $report->status !== 'final') {
            return ReportPeriodStatus::Draft;
        }

        $shares = (int) ($report->shares_count ?? 0);

        return $shares > 0 ? ReportPeriodStatus::Sent : ReportPeriodStatus::Ready;
    }
}
