<?php

declare(strict_types=1);

namespace App\Integrations\BetterUptime;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects Better Stack (Better Uptime) monitors into the shared uptime.* metric
 * layer plus a 'monitors' snapshot (monitor rows + incident list) — the same
 * shape UptimeRobot emits, so the generic uptime blocks render either provider.
 */
class MonitorsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'monitors';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new BetterUptimeClient(
            (string) $connection->credential('api_key'),
            (string) ($connection->setting('base_url') ?: 'https://uptime.betterstack.com'),
        );

        $wanted = $this->monitorIds($connection);

        $availabilitySum = 0.0;
        $availabilityCount = 0;
        $downtimeSeconds = 0;
        $incidentCount = 0;
        $monitorRows = [];

        foreach ($client->monitors() as $monitor) {
            $id = (string) ($monitor['id'] ?? '');
            if ($wanted !== [] && ! in_array($id, $wanted, true)) {
                continue;
            }

            $attributes = (array) ($monitor['attributes'] ?? []);
            $sla = $client->sla($id, $range);

            $availability = (float) ($sla['availability'] ?? 0);
            if ($availability > 0) {
                $availabilitySum += $availability;
                $availabilityCount++;
            }
            $downtimeSeconds += (int) ($sla['total_downtime'] ?? 0);
            $incidentCount += (int) ($sla['number_of_incidents'] ?? 0);

            $monitorRows[] = [
                'id' => $id,
                'name' => (string) ($attributes['pronounceable_name'] ?? $attributes['url'] ?? 'Monitor'),
                'url' => (string) ($attributes['url'] ?? ''),
                'status' => $this->statusLabel((string) ($attributes['status'] ?? '')),
                'uptime' => round($availability, 3),
                'avg_response_ms' => 0,
            ];
        }

        $incidents = array_map(function (array $incident): array {
            $attributes = (array) ($incident['attributes'] ?? []);
            $started = isset($attributes['started_at']) ? CarbonImmutable::parse((string) $attributes['started_at']) : null;
            $resolved = isset($attributes['resolved_at']) && $attributes['resolved_at'] ? CarbonImmutable::parse((string) $attributes['resolved_at']) : null;

            return [
                'monitor' => (string) ($attributes['name'] ?? 'Monitor'),
                'started_at' => $started?->toIso8601String() ?? '',
                'duration_seconds' => ($started && $resolved) ? (int) $started->diffInSeconds($resolved) : 0,
                'reason' => (string) ($attributes['cause'] ?? 'Down'),
            ];
        }, $client->incidents($range));

        return CollectorResult::make()
            ->metric('uptime.percentage', $availabilityCount > 0 ? round($availabilitySum / $availabilityCount, 3) : 0, '%')
            ->metric('uptime.incidents', $incidentCount)
            ->metric('uptime.downtime_seconds', $downtimeSeconds, 'seconds')
            ->metric('uptime.monitors', count($monitorRows))
            ->snapshot([
                'monitors' => $monitorRows,
                'incidents' => $incidents,
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function monitorIds(SiteIntegration $connection): array
    {
        $raw = (string) $connection->setting('monitors', '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'up' => 'up',
            'down' => 'down',
            'paused', 'maintenance' => 'paused',
            default => 'unknown',
        };
    }
}
