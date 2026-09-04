<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Integrations\IntegrationRegistry;
use App\Jobs\RunConnectorCollection;
use App\Models\Metric;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\AuditLogger;
use App\Support\DateRange;
use App\Support\Format;
use App\Support\MetricLabel;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The integrations panel on a site page: shows connected services with their
 * health and lets staff connect, collect, manage or disconnect them.
 */
class SitePanel extends Component
{
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
        $range = DateRange::thisMonth();

        // Queue it rather than collecting in-request — some providers (e.g. GA4)
        // are slow, and blocking makes the page look frozen. Progress shows on
        // the Activity page.
        RunConnectorCollection::dispatch($connection, $range->start->toDateString(), $range->end->toDateString());

        session()->flash('panel_status', 'Collection queued — running in the background. See Activity for progress.');
    }

    public function disconnect(int $connectionId, AuditLogger $audit): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->site->integrations()->findOrFail($connectionId);
        $audit->log('integration.disconnected', $connection, metadata: ['integration' => $connection->integration_key]);
        $connection->delete();

        session()->flash('panel_status', 'Service disconnected.');
    }

    public function render(): mixed
    {
        $registry = app(IntegrationRegistry::class);
        $connections = $this->site->integrations()->orderBy('name')->get();

        return view('livewire.integrations.site-panel', [
            'connections' => $connections,
            'available' => $registry->byCategory(),
            'insights' => $connections->mapWithKeys(
                fn (SiteIntegration $connection): array => [$connection->id => $this->insightFor($connection)],
            )->all(),
        ]);
    }

    /**
     * A compact snapshot for one connection: its latest-period metrics as chips
     * plus a headline metric charted across every period collected so far.
     *
     * @return array{chips: array<int, array{label: string, value: string}>, chart: array{label: string, labels: array<int, string>, data: array<int, float>}}|null
     */
    private function insightFor(SiteIntegration $connection): ?array
    {
        /** @var Collection<int, Metric> $metrics */
        $metrics = $connection->metrics()->orderBy('period_start')->get();
        if ($metrics->isEmpty()) {
            return null;
        }

        $latestPeriod = $metrics->max('period_start');
        $latest = $metrics->where('period_start', $latestPeriod);

        // Headline = the largest latest value, which naturally surfaces the
        // count that matters (pageviews, visitors, impressions…) over rates.
        $headlineKey = (string) $latest->sortByDesc('value')->first()?->metric_key;
        $series = $metrics->where('metric_key', $headlineKey)->sortBy('period_start')->values();

        return [
            'chips' => $latest->map(fn (Metric $metric): array => [
                'label' => MetricLabel::for($metric->metric_key),
                'value' => $this->formatMetric($metric),
            ])->values()->all(),
            'chart' => [
                'label' => MetricLabel::for($headlineKey),
                'labels' => $series->map(fn (Metric $metric): string => $metric->period_start->format('M Y'))->all(),
                'data' => $series->map(fn (Metric $metric): float => round($metric->value, 2))->all(),
            ],
        ];
    }

    private function formatMetric(Metric $metric): string
    {
        return match ($metric->unit) {
            '%' => Format::percent($metric->value, 1),
            'seconds' => Format::duration($metric->value),
            default => Format::number($metric->value),
        };
    }
}
