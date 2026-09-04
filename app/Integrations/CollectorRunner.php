<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Collector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\CollectorRun;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;
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

        return array_map(
            fn (Collector $collector): CollectorRun => $this->run($connection, $collector, $range),
            $integration->collectors(),
        );
    }

    public function run(SiteIntegration $connection, Collector $collector, DateRange $range): CollectorRun
    {
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
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $message = $this->safeMessage($e);

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(CarbonImmutable::now()),
                'error_message' => $message,
            ]);

            $connection->update([
                'status' => ConnectionStatus::NeedsAttention,
                'last_error' => $message,
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

    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof IntegrationException) {
            return mb_substr($e->getMessage(), 0, 500);
        }

        // Never surface arbitrary exception messages: they can leak URLs with
        // tokens or other internals. Report the type only.
        return 'Unexpected error during collection ('.class_basename($e).'). Check the service and try again.';
    }
}
