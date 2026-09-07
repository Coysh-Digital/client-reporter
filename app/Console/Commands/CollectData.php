<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BackgroundTaskStatus;
use App\Enums\ConnectionStatus;
use App\Integrations\CollectionSchedule;
use App\Integrations\CollectorRunner;
use App\Jobs\RunConnectorCollection;
use App\Models\BackgroundTask;
use App\Models\CollectorRun;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\ReportRender;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

    /** A run still "running" after this long was killed with its worker. */
    private const STALE_RUN_MINUTES = 15;

    /** Collection-run history kept for the Activity page. */
    private const RUN_RETENTION_DAYS = 90;

    /** Frozen renders kept per report (the newest is the live one). */
    private const RENDERS_PER_REPORT = 5;

    public function handle(CollectorRunner $runner, CollectionSchedule $schedule): int
    {
        $this->reapStaleRuns();

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
            if (! $history && ! $this->option('force') && ! $schedule->isDue($connection)) {
                continue;
            }

            if ($this->option('sync')) {
                $runner->collectAll($connection, $range);
            } else {
                RunConnectorCollection::queueFor($connection, $range);
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
     * A worker that is killed (out of memory, deploy restart) leaves its run
     * stuck at "running" forever. Close those out so the Activity page and the
     * connection's status reflect what actually happened.
     */
    private function reapStaleRuns(): void
    {
        $stale = CollectorRun::query()
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(self::STALE_RUN_MINUTES))
            ->get();

        foreach ($stale as $run) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => 'The worker stopped before this run finished.',
            ]);
        }

        if ($stale->isNotEmpty()) {
            $this->warn("Closed {$stale->count()} stale collection run(s).");
        }

        // Close background-task records left "running"/"queued" by a worker that
        // stopped, so the activity feed doesn't show zombies.
        BackgroundTask::query()
            ->whereIn('status', BackgroundTaskStatus::activeValues())
            ->where('updated_at', '<', now()->subMinutes(self::STALE_RUN_MINUTES))
            ->update([
                'status' => BackgroundTaskStatus::Failed->value,
                'finished_at' => now(),
                'error' => 'The worker stopped before this task finished.',
            ]);
    }

    /**
     * Delete metrics/snapshots collected before the retention window. Generated
     * reports keep their own frozen snapshots, so pruning only affects
     * re-generating reports for periods now beyond retention. Null = keep all.
     * Run history and superseded renders are always pruned: neither is needed
     * for a report to stay accurate.
     */
    private function pruneExpiredData(): void
    {
        $days = app(Settings::class)->get('collection_retention_days', config('client-reporter.collection.retention_days'));

        if ($days !== null && (int) $days > 0) {
            $cutoff = now()->subDays((int) $days);
            $metrics = Metric::query()->where('captured_at', '<', $cutoff)->delete();
            $snapshots = MetricSnapshot::query()->where('captured_at', '<', $cutoff)->delete();

            if ($metrics > 0 || $snapshots > 0) {
                $this->info("Pruned {$metrics} metric(s) and {$snapshots} snapshot(s) older than {$days} day(s).");
            }
        }

        CollectorRun::query()->where('started_at', '<', now()->subDays(self::RUN_RETENTION_DAYS))->delete();

        // Prune finished background-task records past the activity-feed window.
        BackgroundTask::query()
            ->whereNotIn('status', BackgroundTaskStatus::activeValues())
            ->where('finished_at', '<', now()->subDays((int) config('client-reporter.queue.task_retention_days', 7)))
            ->delete();

        $this->pruneSupersededRenders();
    }

    /**
     * Every generation appends a frozen render; only the newest is ever read.
     * Keep a few per report for safety and drop the rest.
     */
    private function pruneSupersededRenders(): void
    {
        $reportIds = ReportRender::query()
            ->select('report_id')
            ->groupBy('report_id')
            ->havingRaw('count(*) > ?', [self::RENDERS_PER_REPORT])
            ->pluck('report_id');

        foreach ($reportIds as $reportId) {
            $keep = ReportRender::query()
                ->where('report_id', $reportId)
                ->orderByDesc('rendered_at')
                ->orderByDesc('id')
                ->limit(self::RENDERS_PER_REPORT)
                ->pluck('id');

            DB::table('report_renders')
                ->where('report_id', $reportId)
                ->whereNotIn('id', $keep->all())
                ->delete();
        }
    }
}
