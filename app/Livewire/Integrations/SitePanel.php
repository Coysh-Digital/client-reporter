<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Integrations\CollectionSchedule;
use App\Integrations\PageSpeed\PageSpeedIntegration;
use App\Jobs\RunConnectorCollection;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\AuditLogger;
use App\Support\ConnectionState;
use App\Support\DateRange;
use App\Support\Format;
use App\Support\MetricLabel;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The connected-services list on a site page: one compact row per connection
 * with its state, timing, a headline figure and a sparkline. Everything is
 * resolved in a handful of bulk queries regardless of how many connections a
 * site has.
 */
class SitePanel extends Component
{
    /** Points kept for the sparkline (roughly a month of daily values). */
    private const SPARKLINE_POINTS = 30;

    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    #[On('integration-updated')]
    public function refresh(): void
    {
        $this->site->load('integrations');
    }

    public function collectNow(int $connectionId): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->site->integrations()->findOrFail($connectionId);

        // Queue it rather than collecting in-request — some providers (e.g. GA4)
        // are slow, and blocking makes the page look frozen. The row shows
        // "Syncing" until the worker reports back.
        RunConnectorCollection::queueFor($connection, DateRange::thisMonth());

        $this->dispatch('toast', message: 'Collection queued — this row updates when it finishes. See Activity for details.', type: 'ok');
    }

    public function disconnect(int $connectionId, AuditLogger $audit): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->site->integrations()->findOrFail($connectionId);
        $audit->log('integration.disconnected', $connection, metadata: ['integration' => $connection->integration_key]);
        $connection->delete();

        $this->dispatch('toast', message: 'Service disconnected.', type: 'ok');
        $this->dispatch('integration-updated');
    }

    public function render(CollectionSchedule $schedule): mixed
    {
        /** @var Collection<int, SiteIntegration> $connections */
        // workspaceIntegration is eager-loaded because the API-key source note
        // (PageSpeed) reads a workspace-linked connection's shared credentials.
        $connections = $this->site->integrations()->with(['latestRun', 'workspaceIntegration'])->orderBy('name')->get();

        $states = $connections->mapWithKeys(
            fn (SiteIntegration $c): array => [$c->id => ConnectionState::from($c, $schedule)],
        );

        return view('livewire.integrations.site-panel', [
            'connections' => $connections,
            'states' => $states->all(),
            'headlines' => $this->headlines($connections),
            'trends' => $this->trends($connections),
            'apiKeyNotes' => $this->apiKeyNotes($connections),
            'polling' => $states->contains(fn (ConnectionState $s): bool => $s->syncing),
        ]);
    }

    /**
     * Per-connection API-key source, for the integrations that share one key
     * across the workspace (PageSpeed today). Maps a connection id to 'own',
     * 'workspace' or 'none', so the panel can show where its key comes from.
     *
     * @param  Collection<int, SiteIntegration>  $connections
     * @return array<int, string>
     */
    private function apiKeyNotes(Collection $connections): array
    {
        return $connections
            ->filter(fn (SiteIntegration $c): bool => $c->integration_key === 'pagespeed')
            ->mapWithKeys(fn (SiteIntegration $c): array => [$c->id => PageSpeedIntegration::apiKeySource($c)])
            ->all();
    }

    /**
     * One headline figure per connection: the integration's own choice of
     * metric, else the category default, else the largest value collected —
     * for this month, falling back to last month.
     *
     * @param  Collection<int, SiteIntegration>  $connections
     * @return array<int, array{label: string, value: string, period: string}>
     */
    private function headlines(Collection $connections): array
    {
        if ($connections->isEmpty()) {
            return [];
        }

        $current = DateRange::thisMonth();
        $previous = DateRange::lastMonth();

        $rows = Metric::query()
            ->whereIn('site_integration_id', $connections->pluck('id')->all())
            ->where(function ($q) use ($current, $previous): void {
                $q->whereDate('period_start', $current->start->toDateString())
                    ->orWhereDate('period_start', $previous->start->toDateString());
            })
            ->get(['site_integration_id', 'metric_key', 'value', 'unit', 'period_start'])
            ->groupBy('site_integration_id');

        $out = [];
        foreach ($connections as $connection) {
            /** @var Collection<int, Metric> $metrics */
            $metrics = $rows->get($connection->id, collect());
            if ($metrics->isEmpty()) {
                continue;
            }

            $integration = $connection->integration();
            $preferred = $integration?->headlineMetric() ?? $integration?->manifest()->category->defaultHeadlineMetric();

            foreach ([$current, $previous] as $period) {
                $inPeriod = $metrics->filter(fn (Metric $m): bool => $m->period_start->toDateString() === $period->start->toDateString());
                if ($inPeriod->isEmpty()) {
                    continue;
                }

                $metric = $preferred !== null ? $inPeriod->firstWhere('metric_key', $preferred) : null;
                $metric ??= $inPeriod->sortByDesc('value')->first();

                $out[$connection->id] = [
                    'label' => MetricLabel::for($metric->metric_key),
                    'value' => $this->formatMetric($metric),
                    'period' => $period->start->format('M Y'),
                ];
                break;
            }
        }

        return $out;
    }

    /**
     * The newest per-day series for each connection that has one: a sparkline
     * (last 30 points) plus the full series for the expandable chart.
     *
     * @param  Collection<int, SiteIntegration>  $connections
     * @return array<int, array{label: string, labels: array<int, string>, data: array<int, float>, spark: array<int, float>}>
     */
    private function trends(Collection $connections): array
    {
        if ($connections->isEmpty()) {
            return [];
        }

        // Pick the newest timeseries snapshot per connection without loading payloads…
        $candidates = MetricSnapshot::query()
            ->whereIn('site_integration_id', $connections->pluck('id')->all())
            ->where('has_timeseries', true)
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get(['id', 'site_integration_id', 'collector_key', 'period_start'])
            ->unique('site_integration_id');

        if ($candidates->isEmpty()) {
            return [];
        }

        // …then load just those rows' payloads.
        $snapshots = MetricSnapshot::query()->whereIn('id', $candidates->pluck('id')->all())->get();

        $out = [];
        foreach ($snapshots as $snapshot) {
            /** @var array<int, array{date?: string, value?: int|float}> $series */
            $series = $snapshot->payload['timeseries'] ?? [];
            if (count($series) < 2) {
                continue;
            }

            $data = array_map(fn (array $p): float => round((float) ($p['value'] ?? 0), 2), $series);

            $out[$snapshot->site_integration_id] = [
                'label' => $this->dailyLabel($snapshot->collector_key),
                'labels' => array_map(fn (array $p): string => (string) ($p['date'] ?? ''), $series),
                'data' => $data,
                'spark' => array_slice($data, -self::SPARKLINE_POINTS),
            ];
        }

        return $out;
    }

    /**
     * A friendly name for a collector's daily timeseries.
     */
    private function dailyLabel(string $collectorKey): string
    {
        return match ($collectorKey) {
            'summary' => 'Visitors per day',
            'monitors' => 'Daily uptime',
            'core-web-vitals' => 'Performance score',
            'search' => 'Search clicks per day',
            'shopify', 'stripe', 'sales' => 'Revenue per day',
            default => 'Daily trend',
        };
    }

    private function formatMetric(Metric $metric): string
    {
        $unit = (string) $metric->unit;

        return match (true) {
            $unit === '%' => Format::percent($metric->value, 2),
            $unit === 'seconds' => Format::duration($metric->value),
            $unit === 'ms' => Format::number($metric->value).' ms',
            preg_match('/^[A-Z]{3}$/', $unit) === 1 => Format::money($metric->value, $unit),
            default => Format::number($metric->value),
        };
    }
}
