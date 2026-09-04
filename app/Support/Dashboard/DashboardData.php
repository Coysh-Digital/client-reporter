<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\ConnectionStatus;
use App\Enums\ReportPeriodStatus;
use App\Enums\SiteHealth;
use App\Models\Client;
use App\Models\Metric;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Assembles the agency dashboard view-model in one pass: portfolio totals,
 * per-site health, the "needs attention" queue, this period's report status and
 * the biggest metric movers. All figures come from data the hourly collector
 * already warms (this month + last month), so nothing here renders a report.
 */
class DashboardData
{
    /** Metrics surfaced as "notable changes", with display rules. */
    private const MOVERS = [
        'analytics.visitors' => ['label' => 'Visitors', 'higherIsBetter' => true, 'format' => 'percent'],
        'ecommerce.revenue' => ['label' => 'Revenue', 'higherIsBetter' => true, 'format' => 'currency'],
        'uptime.response_time_ms' => ['label' => 'Avg response time', 'higherIsBetter' => false, 'format' => 'ms'],
        'uptime.percentage' => ['label' => 'Uptime', 'higherIsBetter' => true, 'format' => 'uptime'],
    ];

    public function __construct(
        private readonly SiteHealthResolver $health,
        private readonly ReportStatusResolver $reports,
    ) {}

    /**
     * Build the dashboard view-model. $comparison is the period the movers are
     * measured against; for "this month" that must be the calendar last month
     * (the window the hourly collector warms), not a duration-shifted range.
     *
     * @return array<string, mixed>
     */
    public function build(?DateRange $period = null, ?DateRange $comparison = null): array
    {
        $period ??= DateRange::thisMonth();
        $comparison ??= DateRange::lastMonth();

        /** @var Collection<int, Site> $sites */
        $sites = Site::query()->where('is_active', true)->with('client')->get();

        $health = $this->health->forSites($sites, $period);
        $reportStatus = $this->reports->forSites($sites, $period);

        return [
            'period' => $period,
            'portfolio' => $this->portfolio($sites, $health, $reportStatus),
            'needsAttention' => $this->needsAttention($sites, $reportStatus, $period),
            'reportsThisPeriod' => $this->reportsThisPeriod($sites, $reportStatus),
            'notableChanges' => $this->notableChanges($period, $comparison),
        ];
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  array<int, SiteHealth>  $health
     * @param  array<int, array{status: ReportPeriodStatus, report: ?Report}>  $reportStatus
     * @return array<string, mixed>
     */
    private function portfolio(Collection $sites, array $health, array $reportStatus): array
    {
        $healthy = count(array_filter($health, fn (SiteHealth $h): bool => $h === SiteHealth::Healthy));
        $warn = count(array_filter($health, fn (SiteHealth $h): bool => $h === SiteHealth::NeedsAttention));
        $down = count(array_filter($health, fn (SiteHealth $h): bool => $h === SiteHealth::Down));

        $integrations = SiteIntegration::query()
            ->where('status', '!=', ConnectionStatus::NotConnected->value)
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->count();

        $needReconnect = SiteIntegration::query()
            ->whereIn('status', [ConnectionStatus::NeedsAttention->value, ConnectionStatus::Error->value])
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->count();

        $statuses = array_map(fn (array $r): ReportPeriodStatus => $r['status'], $reportStatus);
        $sent = count(array_filter($statuses, fn (ReportPeriodStatus $s): bool => $s === ReportPeriodStatus::Sent));
        $toPrepare = count(array_filter($statuses, fn (ReportPeriodStatus $s): bool => $s === ReportPeriodStatus::NotStarted));

        return [
            'clients' => Client::count(),
            'sitesTotal' => $sites->count(),
            'sitesHealthy' => $healthy,
            'healthSplit' => ['ok' => $healthy, 'warn' => $warn, 'danger' => $down],
            'integrations' => $integrations,
            'integrationsNeedReconnect' => $needReconnect,
            'reportsSent' => $sent,
            'reportsToPrepare' => $toPrepare,
        ];
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  array<int, array{status: ReportPeriodStatus, report: ?Report}>  $reportStatus
     * @return array<int, array<string, mixed>>
     */
    private function needsAttention(Collection $sites, array $reportStatus, DateRange $period): array
    {
        $items = [];

        // Integrations in trouble.
        $troubled = SiteIntegration::query()
            ->whereIn('status', [ConnectionStatus::NeedsAttention->value, ConnectionStatus::Error->value])
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->with('site.client')
            ->get();

        foreach ($troubled as $conn) {
            $integration = $conn->integration();
            $name = $integration?->manifest()->name ?? $conn->integration_key;
            $items[] = [
                'severity' => $conn->status === ConnectionStatus::Error ? 2 : 1,
                'variant' => $conn->status->badge(),
                'title' => $conn->status === ConnectionStatus::Error ? "{$name} sync failed" : "{$name} needs attention",
                'subtitle' => $this->siteLine($conn->site).($conn->last_error ? ' · '.$conn->last_error : ''),
                'when' => $conn->last_collected_at?->diffForHumans() ?? $conn->last_connected_at?->diffForHumans() ?? '',
                'actionLabel' => 'Reconnect',
                'actionUrl' => route('sites.show', $conn->site),
            ];
        }

        // CMS/core updates due (current-period metric).
        foreach ($this->sitesWithUpdatesDue($sites, $period) as $siteId => $count) {
            $site = $sites->firstWhere('id', $siteId);
            if ($site === null) {
                continue;
            }
            $items[] = [
                'severity' => 1,
                'variant' => 'warn',
                'title' => $count === 1 ? '1 update available' : "{$count} updates available",
                'subtitle' => $this->siteLine($site),
                'when' => '',
                'actionLabel' => 'View site',
                'actionUrl' => route('sites.show', $site),
            ];
        }

        // Reports not started for the period.
        foreach ($sites as $site) {
            if (($reportStatus[$site->id]['status'] ?? null) === ReportPeriodStatus::NotStarted) {
                $items[] = [
                    'severity' => 1,
                    'variant' => 'warn',
                    'title' => $period->label().' report not created',
                    'subtitle' => $this->siteLine($site),
                    'when' => '',
                    'actionLabel' => 'Create report',
                    'actionUrl' => route('reports.create'),
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['severity'] <=> $a['severity']);

        return $items;
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  array<int, array{status: ReportPeriodStatus, report: ?Report}>  $reportStatus
     * @return array<int, array<string, mixed>>
     */
    private function reportsThisPeriod(Collection $sites, array $reportStatus): array
    {
        return $sites
            ->map(function (Site $site) use ($reportStatus): array {
                $entry = $reportStatus[$site->id] ?? ['status' => ReportPeriodStatus::NotStarted, 'report' => null];
                /** @var ReportPeriodStatus $status */
                $status = $entry['status'];
                /** @var Report|null $report */
                $report = $entry['report'];

                $url = match ($status) {
                    ReportPeriodStatus::NotStarted => route('reports.create'),
                    ReportPeriodStatus::Draft => $report ? route('reports.edit', $report) : route('reports.create'),
                    default => $report ? route('reports.show', $report) : route('reports.index'),
                };

                return [
                    'client' => $site->client->name,
                    'site' => $site->name,
                    'status' => $status,
                    'actionUrl' => $url,
                ];
            })
            ->sortBy(fn (array $r): int => match ($r['status']) {
                ReportPeriodStatus::NotStarted => 0,
                ReportPeriodStatus::Draft => 1,
                ReportPeriodStatus::Ready => 2,
                ReportPeriodStatus::Sent => 3,
            })
            ->values()
            ->all();
    }

    /**
     * The biggest current-vs-previous metric movers across the portfolio.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notableChanges(DateRange $period, DateRange $previous): array
    {
        // site_integration_id => site (live connections on active sites).
        $connections = SiteIntegration::query()
            ->whereIn('status', [ConnectionStatus::Connected->value, ConnectionStatus::NeedsAttention->value])
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->with('site.client')
            ->get();

        if ($connections->isEmpty()) {
            return [];
        }

        $siteFor = $connections->mapWithKeys(fn (SiteIntegration $c): array => [$c->id => $c->site]);

        $rows = Metric::query()
            ->whereIn('site_integration_id', $connections->pluck('id')->all())
            ->whereIn('metric_key', array_keys(self::MOVERS))
            ->where(function ($q) use ($period, $previous): void {
                $q->where(function ($q) use ($period): void {
                    $q->whereDate('period_start', $period->start->toDateString())
                        ->whereDate('period_end', $period->end->toDateString());
                })->orWhere(function ($q) use ($previous): void {
                    $q->whereDate('period_start', $previous->start->toDateString())
                        ->whereDate('period_end', $previous->end->toDateString());
                });
            })
            ->get(['site_integration_id', 'metric_key', 'value', 'unit', 'period_start']);

        // Group into [siteId][metricKey] => ['current' => .., 'previous' => .., 'unit' => ..]
        $grouped = [];
        foreach ($rows as $row) {
            $site = $siteFor[$row->site_integration_id] ?? null;
            if ($site === null) {
                continue;
            }
            $isCurrent = CarbonImmutable::parse($row->period_start)->toDateString() === $period->start->toDateString();
            $slot = $isCurrent ? 'current' : 'previous';
            $grouped[$site->id][$row->metric_key][$slot] = (float) $row->value;
            $grouped[$site->id][$row->metric_key]['unit'] ??= $row->unit;
            $grouped[$site->id][$row->metric_key]['site'] = $site;
        }

        $movers = [];
        foreach ($grouped as $metrics) {
            foreach ($metrics as $key => $data) {
                $current = $data['current'] ?? null;
                $previousVal = $data['previous'] ?? null;
                if ($current === null || $previousVal === null || $previousVal == 0.0) {
                    continue;
                }
                $delta = $current - $previousVal;
                if ($delta == 0.0) {
                    continue;
                }
                $pct = $delta / abs($previousVal);
                $config = self::MOVERS[$key];
                $up = $delta > 0;
                $movers[] = [
                    'site' => $data['site']->name,
                    'metricLabel' => $config['label'],
                    'text' => ($up ? '▲ ' : '▼ ').$this->formatDelta($config['format'], $delta, $current, $previousVal, $data['unit'] ?? null),
                    'variant' => ($up === $config['higherIsBetter']) ? 'ok' : 'danger',
                    'magnitude' => abs($pct),
                ];
            }
        }

        usort($movers, fn (array $a, array $b): int => $b['magnitude'] <=> $a['magnitude']);

        return array_slice($movers, 0, 5);
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return array<int, int> site id => updates due count
     */
    private function sitesWithUpdatesDue(Collection $sites, DateRange $period): array
    {
        $integrations = SiteIntegration::query()
            ->whereIn('site_id', $sites->pluck('id')->all())
            ->whereIn('status', [ConnectionStatus::Connected->value, ConnectionStatus::NeedsAttention->value])
            ->get(['id', 'site_id']);

        if ($integrations->isEmpty()) {
            return [];
        }

        $siteFor = $integrations->mapWithKeys(fn (SiteIntegration $i): array => [$i->id => $i->site_id]);

        $rows = Metric::query()
            ->whereIn('site_integration_id', $integrations->pluck('id')->all())
            ->where('metric_key', 'cms.updates_total')
            ->where('value', '>', 0)
            ->whereDate('period_start', $period->start->toDateString())
            ->whereDate('period_end', $period->end->toDateString())
            ->get(['site_integration_id', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $siteId = $siteFor[$row->site_integration_id] ?? null;
            if ($siteId === null) {
                continue;
            }
            $out[$siteId] = ($out[$siteId] ?? 0) + (int) $row->value;
        }

        return $out;
    }

    private function siteLine(?Site $site): string
    {
        if ($site === null) {
            return '';
        }

        $client = $site->client?->name;

        return trim(($client ? $client.' · ' : '').$site->host());
    }

    private function formatDelta(string $format, float $delta, float $current, float $previous, ?string $unit): string
    {
        return match ($format) {
            'percent' => rtrim(rtrim(number_format(abs($delta / abs($previous)) * 100, 1), '0'), '.').'%',
            'currency' => $this->currencySymbol($unit).number_format(abs($delta)),
            'ms' => number_format(abs($delta)).'ms',
            'uptime' => number_format($current, 2).'%',
            default => number_format(abs($delta)),
        };
    }

    private function currencySymbol(?string $code): string
    {
        return match (strtoupper((string) $code)) {
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
            default => $code ? $code.' ' : '',
        };
    }
}
