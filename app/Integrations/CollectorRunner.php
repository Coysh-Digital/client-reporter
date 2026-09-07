<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Collector;
use App\Integrations\Support\AuthenticationException;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\RateLimitedException;
use App\Models\CollectorRun;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use App\Support\SafeError;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes collectors and persists their output, recording each run and
 * translating failures into safe, actionable connection states. A single
 * collector failure never throws out of here — it is captured on the run and
 * reflected on the connection, so other collectors and connections keep working
 * and previously collected report data remains available.
 */
class CollectorRunner
{
    /**
     * Run every collector for a connection's integration over the given range.
     *
     * @return array<int, CollectorRun>
     */
    public function collectAll(SiteIntegration $connection, DateRange $range): array
    {
        $integration = $connection->integration();

        if ($integration === null) {
            return [];
        }

        $runs = array_map(
            fn (Collector $collector): CollectorRun => $this->run($connection, $collector, $range),
            $integration->collectors(),
        );

        $this->settleFailureCount($connection, $runs);

        return $runs;
    }

    /**
     * Track consecutive failed collection passes once per pass (not once per
     * collector), and auto-disable the connection when it crosses the threshold
     * so it stops being retried until someone reconnects. A pass counts as a
     * failure only when nothing at all was collected; any success resets it.
     *
     * @param  array<int, CollectorRun>  $runs
     */
    private function settleFailureCount(SiteIntegration $connection, array $runs): void
    {
        if ($runs === []) {
            return;
        }

        $anySucceeded = array_filter($runs, fn (CollectorRun $run): bool => $run->status === 'success') !== [];

        if ($anySucceeded) {
            // run() already set the connection Connected and cleared the error;
            // just make sure the failure count and disabled marker are cleared.
            if ($connection->consecutive_failures !== 0 || $connection->disabled_at !== null) {
                $connection->update(['consecutive_failures' => 0, 'disabled_at' => null]);
            }

            return;
        }

        $failures = $connection->consecutive_failures + 1;
        $update = ['consecutive_failures' => $failures];

        if ($failures >= $this->failureThreshold()) {
            $update['status'] = ConnectionStatus::Disabled;
            $update['disabled_at'] = now();
        }

        $connection->update($update);
    }

    private function failureThreshold(): int
    {
        return max(1, (int) config('client-reporter.collection.failure_threshold', 5));
    }

    public function run(SiteIntegration $connection, Collector $collector, DateRange $range): CollectorRun
    {
        // The queued marker has served its purpose once a worker is here.
        if ($connection->collection_queued_at !== null) {
            $connection->forceFill(['collection_queued_at' => null])->save();
        }

        $run = $connection->collectorRuns()->create([
            'collector_key' => $collector->key(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startedAt = CarbonImmutable::now();

        try {
            $result = $collector->collect($connection, $range);
            $written = $this->persist($connection, $collector, $range, $result);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(CarbonImmutable::now()),
                'records_written' => $written,
                'error_message' => null,
            ]);

            $connection->update([
                'status' => ConnectionStatus::Connected,
                'last_connected_at' => now(),
                'last_collected_at' => now(),
                'last_attempted_at' => now(),
                'last_error' => null,
                'last_failure_kind' => null,
            ]);
        } catch (Throwable $e) {
            $message = $this->safeMessage($e);

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(CarbonImmutable::now()),
                'error_message' => $message,
            ]);

            // Rejected credentials stop scheduled collection until someone
            // reconnects; anything else is worth another try next interval.
            [$status, $kind] = match (true) {
                $e instanceof AuthenticationException => [ConnectionStatus::AuthExpired, 'auth'],
                $e instanceof RateLimitedException => [ConnectionStatus::NeedsAttention, 'rate_limit'],
                default => [ConnectionStatus::NeedsAttention, 'failed'],
            };

            $connection->update([
                'status' => $status,
                'last_attempted_at' => now(),
                'last_error' => $message,
                'last_failure_kind' => $kind,
            ]);

            Log::warning('Collector run failed', [
                'connection_id' => $connection->id,
                'integration' => $connection->integration_key,
                'collector' => $collector->key(),
                'error' => $message,
            ]);
        }

        return $run->refresh();
    }

    /**
     * Persist a collector result, upserting by period so re-collection updates
     * in place rather than duplicating.
     */
    private function persist(SiteIntegration $connection, Collector $collector, DateRange $range, CollectorResult $result): int
    {
        $written = 0;

        foreach ($result->metrics() as $metric) {
            Metric::query()->updateOrCreate(
                [
                    'site_integration_id' => $connection->id,
                    'metric_key' => $metric->key,
                    'period_start' => $range->start->startOfDay(),
                    'period_end' => $range->end->startOfDay(),
                ],
                [
                    'value' => $metric->value,
                    'unit' => $metric->unit,
                    'meta' => $metric->meta ?: null,
                    'captured_at' => now(),
                ],
            );
            $written++;
        }

        if ($result->hasSnapshot()) {
            MetricSnapshot::query()->updateOrCreate(
                [
                    'site_integration_id' => $connection->id,
                    'collector_key' => $collector->key(),
                    'period_start' => $range->start->startOfDay(),
                    'period_end' => $range->end->startOfDay(),
                ],
                [
                    'granularity' => $result->granularityValue(),
                    'payload' => $result->snapshotPayload(),
                    'captured_at' => now(),
                ],
            );
            $written++;
        }

        return $written;
    }

    /**
     * Never surface arbitrary exception messages: they can leak URLs with
     * tokens or other internals. Only deliberately written messages pass.
     */
    private function safeMessage(Throwable $e): string
    {
        return SafeError::message($e, 'Unexpected error during collection');
    }
}
