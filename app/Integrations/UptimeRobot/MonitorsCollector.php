<?php

declare(strict_types=1);

namespace App\Integrations\UptimeRobot;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects uptime, incidents and response times for the monitors associated
 * with a site, producing client-friendly metrics rather than raw logs.
 */
class MonitorsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'monitors';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new UptimeRobotClient((string) $connection->credential('api_key'));

        $monitorIds = $this->monitorIds($connection);
        $customRange = $range->start->getTimestamp().'_'.$range->end->getTimestamp();

        $monitors = $client->monitors($monitorIds, $customRange, withLogs: true);

        $result = CollectorResult::make();

        $uptimeSum = 0.0;
        $uptimeCount = 0;
        $responseSum = 0.0;
        $responseCount = 0;
        $incidentCount = 0;
        $downtimeSeconds = 0;
        $monitorRows = [];
        $incidents = [];

        foreach ($monitors as $monitor) {
            $uptime = (float) ($monitor['custom_uptime_ranges'] ?? 0);
            if ($uptime > 0) {
                $uptimeSum += $uptime;
                $uptimeCount++;
            }

            $avgResponse = (float) ($monitor['average_response_time'] ?? 0);
            if ($avgResponse > 0) {
                $responseSum += $avgResponse;
                $responseCount++;
            }

            foreach ($monitor['logs'] ?? [] as $log) {
                // type 1 = down event.
                if ((int) ($log['type'] ?? 0) !== 1) {
                    continue;
                }

                $startedAt = CarbonImmutable::createFromTimestamp((int) ($log['datetime'] ?? 0));
                if (! $range->contains($startedAt)) {
                    continue;
                }

                $duration = (int) ($log['duration'] ?? 0);
                $incidentCount++;
                $downtimeSeconds += $duration;

                $incidents[] = [
                    'monitor' => (string) ($monitor['friendly_name'] ?? 'Monitor'),
                    'started_at' => $startedAt->toIso8601String(),
                    'duration_seconds' => $duration,
                    'reason' => (string) data_get($log, 'reason.detail', 'Down'),
                ];
            }

            $monitorRows[] = [
                'id' => $monitor['id'] ?? null,
                'name' => (string) ($monitor['friendly_name'] ?? 'Monitor'),
                'url' => (string) ($monitor['url'] ?? ''),
                'status' => $this->statusLabel((int) ($monitor['status'] ?? 0)),
                'uptime' => round($uptime, 3),
                'avg_response_ms' => (int) round($avgResponse),
            ];
        }

        $result
            ->metric('uptime.percentage', $uptimeCount > 0 ? round($uptimeSum / $uptimeCount, 3) : 0, '%')
            ->metric('uptime.incidents', $incidentCount)
            ->metric('uptime.downtime_seconds', $downtimeSeconds, 'seconds')
            ->metric('uptime.response_time_ms', $responseCount > 0 ? (int) round($responseSum / $responseCount) : 0, 'ms')
            ->metric('uptime.monitors', count($monitorRows))
            ->snapshot([
                'monitors' => $monitorRows,
                'incidents' => $incidents,
            ]);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function monitorIds(SiteIntegration $connection): array
    {
        $raw = (string) $connection->setting('monitors', '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            2 => 'up',
            8, 9 => 'down',
            0 => 'paused',
            default => 'unknown',
        };
    }
}
