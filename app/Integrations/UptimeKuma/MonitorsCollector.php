<?php

declare(strict_types=1);

namespace App\Integrations\UptimeKuma;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects uptime, incidents and response times from a self-hosted Uptime
 * Kuma instance.
 *
 * Unlike UptimeRobot/Better Uptime, Kuma exposes no "give me the aggregate for
 * this date range" API — only the current status of each monitor. So this
 * collector maintains its own rolling history: each time it runs for the
 * *current, still-open* period, it polls Kuma live and appends one sample
 * (plus detects up/down transitions to open or close incidents); for any
 * other (already-elapsed) period it only replays what was already recorded.
 * The history is kept in a single, ever-growing MetricSnapshot row under a
 * sentinel collector key/period, separate from the per-report-period output
 * this collector also produces under its own key ("monitors").
 *
 * A practical consequence: Kuma-reported uptime is only accurate from the
 * point a site was connected forward — there is no way to backfill history
 * for a period before that, unlike UptimeRobot.
 */
class MonitorsCollector extends AbstractCollector
{
    private const LOG_COLLECTOR_KEY = 'monitors_log';

    private const LOG_PERIOD_START = '2000-01-01';

    private const LOG_PERIOD_END = '2099-12-31';

    private const MAX_SAMPLES = 2000;

    private const MAX_LOG_AGE_DAYS = 400;

    public function key(): string
    {
        return 'monitors';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $log = $this->loadLog($connection);

        if ($this->isLivePeriod($range)) {
            $log = $this->pollAndAppend($connection, $log);
            $this->prune($log);
            $this->saveLog($connection, $log);
        }

        return $this->buildResult($log, $range);
    }

    private function isLivePeriod(DateRange $range): bool
    {
        return $range->end->isFuture() || $range->end->isToday();
    }

    /**
     * @return array{samples: array<int, array{at: string, up: bool, response_ms: ?int}>, incidents: array<int, array{monitor: string, started_at: string, ended_at: ?string, reason: string}>, monitors: array<int, array{name: string, url: ?string, status: string}>}
     */
    private function loadLog(SiteIntegration $connection): array
    {
        $payload = MetricSnapshot::query()
            ->where('site_integration_id', $connection->id)
            ->where('collector_key', self::LOG_COLLECTOR_KEY)
            ->whereDate('period_start', self::LOG_PERIOD_START)
            ->whereDate('period_end', self::LOG_PERIOD_END)
            ->first()?->payload;

        return [
            'samples' => $payload['samples'] ?? [],
            'incidents' => $payload['incidents'] ?? [],
            'monitors' => $payload['monitors'] ?? [],
        ];
    }

    private function saveLog(SiteIntegration $connection, array $log): void
    {
        // updateOrCreate's match must use actual Carbon instances (as
        // CollectorRunner::persist() does), not plain date strings — the
        // 'date' cast stores period_start/period_end with a time component,
        // so a bare string match here would never find the existing row and
        // would instead collide with it on insert.
        MetricSnapshot::query()->updateOrCreate(
            [
                'site_integration_id' => $connection->id,
                'collector_key' => self::LOG_COLLECTOR_KEY,
                'period_start' => CarbonImmutable::parse(self::LOG_PERIOD_START)->startOfDay(),
                'period_end' => CarbonImmutable::parse(self::LOG_PERIOD_END)->startOfDay(),
            ],
            ['granularity' => 'range', 'payload' => $log, 'captured_at' => now()],
        );
    }

    private function pollAndAppend(SiteIntegration $connection, array $log): array
    {
        $client = UptimeKumaIntegration::clientFor($connection);

        $monitors = $client->monitors($this->monitorNames($connection));

        if ($monitors === []) {
            return $log;
        }

        $now = CarbonImmutable::now();

        // Only status 1 (up) and 0 (down) count towards uptime — pending and
        // maintenance windows are excluded rather than counted as downtime.
        $counted = array_values(array_filter($monitors, fn (array $m): bool => in_array($m['status'], [0, 1], true)));

        $responses = array_values(array_filter(array_map(fn (array $m) => $m['response_time_ms'], $monitors), fn ($v) => $v !== null));

        $log['samples'][] = [
            'at' => $now->toIso8601String(),
            'up' => $counted === [] ? null : count(array_filter($counted, fn (array $m): bool => $m['status'] === 1)) / count($counted),
            'response_ms' => $responses === [] ? null : (int) round(array_sum($responses) / count($responses)),
        ];

        $log['incidents'] = $this->applyTransitions($log['incidents'], $counted, $now);

        $log['monitors'] = array_map(fn (array $m): array => [
            'name' => $m['name'],
            'url' => $m['url'],
            'status' => match ($m['status']) {
                1 => 'up',
                0 => 'down',
                2 => 'pending',
                3 => 'maintenance',
                default => 'unknown',
            },
        ], $monitors);

        return $log;
    }

    /**
     * @param  array<int, array{monitor: string, started_at: string, ended_at: ?string, reason: string}>  $incidents
     * @param  array<int, array{name: string, status: int}>  $counted
     * @return array<int, array{monitor: string, started_at: string, ended_at: ?string, reason: string}>
     */
    private function applyTransitions(array $incidents, array $counted, CarbonImmutable $now): array
    {
        foreach ($counted as $monitor) {
            $openIndex = null;
            foreach ($incidents as $i => $incident) {
                if ($incident['monitor'] === $monitor['name'] && $incident['ended_at'] === null) {
                    $openIndex = $i;
                    break;
                }
            }

            if ($monitor['status'] === 0 && $openIndex === null) {
                // Went down: open a new incident.
                $incidents[] = [
                    'monitor' => $monitor['name'],
                    'started_at' => $now->toIso8601String(),
                    'ended_at' => null,
                    'reason' => 'Down',
                ];
            } elseif ($monitor['status'] === 1 && $openIndex !== null) {
                // Came back up: close it.
                $incidents[$openIndex]['ended_at'] = $now->toIso8601String();
            }
        }

        return $incidents;
    }

    private function buildResult(array $log, DateRange $range): CollectorResult
    {
        $samplesInRange = array_values(array_filter(
            $log['samples'],
            fn (array $s): bool => $range->contains(CarbonImmutable::parse($s['at'])),
        ));

        $upFractions = array_values(array_filter(array_map(fn (array $s) => $s['up'], $samplesInRange), fn ($v) => $v !== null));
        $responseSamples = array_values(array_filter(array_map(fn (array $s) => $s['response_ms'], $samplesInRange), fn ($v) => $v !== null));

        $percentage = $upFractions === [] ? 0.0 : round((array_sum($upFractions) / count($upFractions)) * 100, 3);
        $responseMs = $responseSamples === [] ? 0 : (int) round(array_sum($responseSamples) / count($responseSamples));

        $incidentsInRange = [];
        $downtimeSeconds = 0;

        foreach ($log['incidents'] as $incident) {
            $startedAt = CarbonImmutable::parse($incident['started_at']);
            $endedAt = $incident['ended_at'] !== null ? CarbonImmutable::parse($incident['ended_at']) : CarbonImmutable::now();

            // No overlap at all with the requested range.
            if ($startedAt->greaterThan($range->end) || $endedAt->lessThan($range->start)) {
                continue;
            }

            $overlapStart = $startedAt->max($range->start);
            $overlapEnd = $endedAt->min($range->end);
            $duration = max(0, (int) $overlapStart->diffInSeconds($overlapEnd));
            $downtimeSeconds += $duration;

            $incidentsInRange[] = [
                'monitor' => $incident['monitor'],
                'started_at' => $startedAt->toIso8601String(),
                'duration_seconds' => $duration,
                'reason' => $incident['reason'],
            ];
        }

        return CollectorResult::make()
            ->metric('uptime.percentage', $percentage, '%')
            ->metric('uptime.incidents', count($incidentsInRange))
            ->metric('uptime.downtime_seconds', $downtimeSeconds, 'seconds')
            ->metric('uptime.response_time_ms', $responseMs, 'ms')
            ->metric('uptime.monitors', count($log['monitors']))
            ->snapshot([
                'monitors' => array_map(fn (array $m): array => [
                    'id' => $m['name'],
                    'name' => $m['name'],
                    'url' => $m['url'] ?? '',
                    'status' => $m['status'],
                    'uptime' => $percentage,
                    'avg_response_ms' => $responseMs,
                ], $log['monitors']),
                'incidents' => $incidentsInRange,
                'timeseries' => $this->dailyUptime($samplesInRange, $range),
            ]);
    }

    /**
     * Average up-fraction per day, as a percentage, across every day in the
     * range (0 where no samples fell on that day — reads as no data, not
     * necessarily downtime, since the chart only spans days actually
     * connected).
     *
     * @param  array<int, array{at: string, up: ?float, response_ms: ?int}>  $samples
     * @return array<int, array{date: string, value: float}>
     */
    private function dailyUptime(array $samples, DateRange $range): array
    {
        $byDay = [];
        foreach ($samples as $sample) {
            if ($sample['up'] === null) {
                continue;
            }
            $day = CarbonImmutable::parse($sample['at'])->toDateString();
            $byDay[$day][] = $sample['up'];
        }

        $days = [];
        $cursor = $range->start;

        while ($cursor->lessThanOrEqualTo($range->end)) {
            $day = $cursor->toDateString();
            $dayFractions = $byDay[$day] ?? [];
            $days[] = [
                'date' => $day,
                'value' => $dayFractions === [] ? 0.0 : round((array_sum($dayFractions) / count($dayFractions)) * 100, 2),
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    private function prune(array &$log): void
    {
        $cutoff = CarbonImmutable::now()->subDays(self::MAX_LOG_AGE_DAYS);

        $log['samples'] = array_values(array_filter(
            $log['samples'],
            fn (array $s): bool => CarbonImmutable::parse($s['at'])->greaterThan($cutoff),
        ));

        if (count($log['samples']) > self::MAX_SAMPLES) {
            $log['samples'] = array_slice($log['samples'], -self::MAX_SAMPLES);
        }

        $log['incidents'] = array_values(array_filter(
            $log['incidents'],
            fn (array $i): bool => $i['ended_at'] === null || CarbonImmutable::parse($i['ended_at'])->greaterThan($cutoff),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function monitorNames(SiteIntegration $connection): array
    {
        $raw = (string) $connection->setting('monitors', '');

        return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $raw) ?: [])));
    }
}
