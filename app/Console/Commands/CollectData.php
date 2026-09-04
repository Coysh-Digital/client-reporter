<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectorRunner;
use App\Jobs\RunConnectorCollection;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Drives scheduled data collection. Keeps the two most common report periods
 * (this month and last month) warm for every live connection that is due,
 * building history and keeping the dashboard fresh.
 */
class CollectData extends Command
{
    protected $signature = 'client-reporter:collect
        {--sync : Run collectors immediately instead of queueing}
        {--force : Collect even if a connection is not yet due}
        {--connection= : Limit to a single connection id}';

    protected $description = 'Collect data from connected integrations';

    public function handle(CollectorRunner $runner): int
    {
        $connections = SiteIntegration::query()
            ->whereIn('status', [ConnectionStatus::Connected->value, ConnectionStatus::NeedsAttention->value])
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->when($this->option('connection'), fn ($q) => $q->whereKey($this->option('connection')))
            ->with('site')
            ->get();

        $periods = [DateRange::thisMonth(), DateRange::lastMonth()];
        $dispatched = 0;

        foreach ($connections as $connection) {
            if (! $this->option('force') && ! $this->isDue($connection)) {
                continue;
            }

            foreach ($periods as $range) {
                if ($this->option('sync')) {
                    $runner->collectAll($connection, $range);
                } else {
                    RunConnectorCollection::dispatch($connection, $range->start->toDateString(), $range->end->toDateString());
                }
            }

            $dispatched++;
        }

        $verb = $this->option('sync') ? 'Collected' : 'Queued collection for';
        $this->info("{$verb} {$dispatched} connection(s).");

        $this->pruneExpiredData();

        return self::SUCCESS;
    }

    private function isDue(SiteIntegration $connection): bool
    {
        if ($connection->last_collected_at === null) {
            return true;
        }

        $interval = (int) app(Settings::class)->get(
            'collection_interval',
            config('client-reporter.collection.default_interval', 360),
        );

        return $connection->last_collected_at->addMinutes($interval)->isPast();
    }

    /**
     * Delete metrics/snapshots collected before the retention window. Generated
     * reports keep their own frozen snapshots, so pruning only affects
     * re-generating reports for periods now beyond retention. Null = keep all.
     */
    private function pruneExpiredData(): void
    {
        $days = app(Settings::class)->get('collection_retention_days', config('client-reporter.collection.retention_days'));

        if ($days === null || (int) $days <= 0) {
            return;
        }

        $cutoff = now()->subDays((int) $days);
        $metrics = Metric::query()->where('captured_at', '<', $cutoff)->delete();
        $snapshots = MetricSnapshot::query()->where('captured_at', '<', $cutoff)->delete();

        if ($metrics > 0 || $snapshots > 0) {
            $this->info("Pruned {$metrics} metric(s) and {$snapshots} snapshot(s) older than {$days} day(s).");
        }
    }
}
