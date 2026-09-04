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
 * Drives scheduled data collection. By default it keeps the current month warm
 * for every live connection that is due, building history and keeping the
 * dashboard fresh. The `--history` mode collects the previous month instead:
 * that is a completed, stable period, so the scheduler runs it just once a day
 * rather than re-collecting it every cycle alongside the current month.
 */
class CollectData extends Command
{
    protected $signature = 'client-reporter:collect
        {--sync : Run collectors immediately instead of queueing}
        {--force : Collect even if a connection is not yet due}
        {--history : Collect the previous (completed) month instead of the current one}
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

        $history = (bool) $this->option('history');
        $range = $history ? DateRange::lastMonth() : DateRange::thisMonth();
        $dispatched = 0;

        foreach ($connections as $connection) {
            // The previous month is stable, so history mode runs on its own daily
            // cadence and always collects; the current month respects the interval.
            if (! $history && ! $this->option('force') && ! $this->isDue($connection)) {
                continue;
            }

            if ($this->option('sync')) {
                $runner->collectAll($connection, $range);
            } else {
                RunConnectorCollection::dispatch($connection, $range->start->toDateString(), $range->end->toDateString());
            }

            $dispatched++;
        }

        $verb = $this->option('sync') ? 'Collected' : 'Queued collection for';
        $period = $history ? 'previous month' : 'current month';
        $this->info("{$verb} {$dispatched} connection(s) ({$period}).");

        $this->pruneExpiredData();

        return self::SUCCESS;
    }

    /**
     * Whether a connection is due to be collected. Keyed off the last *attempt*
     * (falling back to the last success for rows predating that column), so a
     * connection that keeps failing backs off to the normal interval instead of
     * being retried on every scheduler tick.
     */
    private function isDue(SiteIntegration $connection): bool
    {
        $last = $connection->last_attempted_at ?? $connection->last_collected_at;
        if ($last === null) {
            return true;
        }

        $interval = (int) app(Settings::class)->get(
            'collection_interval',
            config('client-reporter.collection.default_interval', 360),
        );

        return $last->addMinutes($interval)->isPast();
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
