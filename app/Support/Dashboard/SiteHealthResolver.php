<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\ConnectionStatus;
use App\Enums\SiteHealth;
use App\Models\Metric;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Support\Collection;

/**
 * Derives a site's operational health from signals already collected: its
 * integration connection states plus the current period's uptime and CMS-update
 * metrics. Bulk resolution avoids per-site queries for the dashboard rollup.
 */
class SiteHealthResolver
{
    /** Below this uptime %, a site is treated as down. */
    private const UPTIME_DOWN_THRESHOLD = 95.0;

    /** Below this uptime % (but at/above down), a site needs attention. */
    private const UPTIME_WARN_THRESHOLD = 99.0;

    /**
     * Resolve health for a single site over a period.
     */
    public function for(Site $site, ?DateRange $period = null): SiteHealth
    {
        return $this->forSites(collect([$site]), $period)[$site->id] ?? SiteHealth::Healthy;
    }

    /**
     * Resolve health for many sites at once.
     *
     * @param  Collection<int, Site>  $sites
     * @return array<int, SiteHealth> keyed by site id
     */
    public function forSites(Collection $sites, ?DateRange $period = null): array
    {
        $period ??= DateRange::thisMonth();
        $siteIds = $sites->pluck('id')->all();

        if ($siteIds === []) {
            return [];
        }

        $integrations = SiteIntegration::query()
            ->whereIn('site_id', $siteIds)
            ->get(['id', 'site_id', 'status']);

        // site_integration_id => site_id, for live connections only.
        $liveMap = $integrations
            ->filter(fn (SiteIntegration $i): bool => $i->status->isLive())
            ->mapWithKeys(fn (SiteIntegration $i): array => [$i->id => $i->site_id]);

        $metricsBySite = $this->currentMetricsBySite($liveMap, $period);
        $integrationsBySite = $integrations->groupBy('site_id');

        $out = [];
        foreach ($sites as $site) {
            $conns = $integrationsBySite->get($site->id, collect());
            $hasError = $conns->contains(fn (SiteIntegration $i): bool => $i->status === ConnectionStatus::Error);
            $hasWarn = $conns->contains(fn (SiteIntegration $i): bool => $i->status === ConnectionStatus::NeedsAttention);

            $m = $metricsBySite[$site->id] ?? [];
            $uptime = $m['uptime.percentage'] ?? null;
            $incidents = $m['uptime.incidents'] ?? 0.0;
            $updates = $m['cms.updates_total'] ?? 0.0;

            $out[$site->id] = match (true) {
                $hasError || ($uptime !== null && $uptime < self::UPTIME_DOWN_THRESHOLD) => SiteHealth::Down,
                $hasWarn || $updates > 0 || $incidents > 0 || ($uptime !== null && $uptime < self::UPTIME_WARN_THRESHOLD) => SiteHealth::NeedsAttention,
                default => SiteHealth::Healthy,
            };
        }

        return $out;
    }

    /**
     * Current-period uptime/CMS metrics, reduced per site.
     *
     * @param  Collection<int, int>  $liveMap  site_integration_id => site_id
     * @return array<int, array<string, float>>
     */
    private function currentMetricsBySite(Collection $liveMap, DateRange $period): array
    {
        if ($liveMap->isEmpty()) {
            return [];
        }

        $rows = Metric::query()
            ->whereIn('site_integration_id', $liveMap->keys()->all())
            ->whereIn('metric_key', ['uptime.percentage', 'uptime.incidents', 'cms.updates_total'])
            ->whereDate('period_start', $period->start->toDateString())
            ->whereDate('period_end', $period->end->toDateString())
            ->get(['site_integration_id', 'metric_key', 'value']);

        $bySite = [];
        foreach ($rows as $row) {
            $siteId = $liveMap[$row->site_integration_id] ?? null;
            if ($siteId === null) {
                continue;
            }
            $key = $row->metric_key;
            $value = (float) $row->value;

            // Reduce across a site's connections: worst uptime, summed incidents/updates.
            if ($key === 'uptime.percentage') {
                $bySite[$siteId][$key] = isset($bySite[$siteId][$key]) ? min($bySite[$siteId][$key], $value) : $value;
            } else {
                $bySite[$siteId][$key] = ($bySite[$siteId][$key] ?? 0.0) + $value;
            }
        }

        return $bySite;
    }
}
