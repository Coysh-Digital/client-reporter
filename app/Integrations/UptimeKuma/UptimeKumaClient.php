<?php

declare(strict_types=1);

namespace App\Integrations\UptimeKuma;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reads monitor data from a self-hosted Uptime Kuma instance's
 * Prometheus-compatible `/metrics` endpoint: each monitor's current status and
 * response time, plus — on newer Kuma versions — real uptime-ratio and average
 * response-time aggregates over 1d/30d/365d windows. Kuma still has no
 * query-by-arbitrary-date-range API, so {@see MonitorsCollector} also keeps its
 * own rolling sample history (for incident detection and a daily timeseries),
 * but prefers these real aggregates for the headline figures when present.
 */
class UptimeKumaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Current status of every monitor exposed by the API key, optionally
     * filtered to a set of monitor names. Newer Uptime Kuma also exposes real
     * aggregates — an uptime ratio and average response time over 1d/30d/365d
     * windows — which are read here (null on older versions that don't).
     *
     * @param  array<int, string>  $onlyNames
     * @return array<int, array{name: string, url: ?string, status: int, response_time_ms: ?float, uptime_ratio: ?float, avg_response_ms: ?int, cert_days: ?int}>
     */
    public function monitors(array $onlyNames = []): array
    {
        $metrics = $this->parseMetrics($this->fetch());

        $byName = [];

        foreach ($metrics['monitor_status'] ?? [] as $row) {
            $name = $row['labels']['monitor_name'] ?? null;
            if ($name === null) {
                continue;
            }

            if ($onlyNames !== [] && ! in_array($name, $onlyNames, true)) {
                continue;
            }

            $byName[$name] = [
                'name' => $name,
                'url' => $row['labels']['monitor_url'] ?? null,
                // 1 = up, 0 = down, 2 = pending, 3 = maintenance.
                'status' => (int) $row['value'],
                'response_time_ms' => null,
                'uptime_ratio' => null,
                'avg_response_ms' => null,
                'cert_days' => null,
            ];
        }

        foreach ($metrics['monitor_response_time'] ?? [] as $row) {
            $name = $row['labels']['monitor_name'] ?? null;
            if ($name === null || ! isset($byName[$name])) {
                continue;
            }

            $byName[$name]['response_time_ms'] = $row['value'];
        }

        // TLS certificate days remaining, for cert-expiry alerts.
        foreach ($metrics['monitor_cert_days_remaining'] ?? [] as $row) {
            $name = $row['labels']['monitor_name'] ?? null;
            if ($name === null || ! isset($byName[$name])) {
                continue;
            }

            $byName[$name]['cert_days'] = (int) $row['value'];
        }

        // Kuma's own uptime ratio (0..1) and average response time (seconds),
        // preferring the 30-day window as the closest match to a monthly report.
        $this->applyWindowed($metrics['monitor_uptime_ratio'] ?? [], $byName, 'uptime_ratio', fn (float $v): float => $v);
        $this->applyWindowed($metrics['monitor_response_time_seconds'] ?? [], $byName, 'avg_response_ms', fn (float $v): int => (int) round($v * 1000));

        return array_values($byName);
    }

    /**
     * Fold windowed Kuma metrics (labelled window="1d|30d|365d") onto each
     * monitor, choosing the most report-appropriate window that's present.
     *
     * @param  array<int, array{labels: array<string, string>, value: float}>  $rows
     * @param  array<string, array<string, mixed>>  $byName
     */
    private function applyWindowed(array $rows, array &$byName, string $target, callable $transform): void
    {
        $preferred = ['30d', '1d', '365d'];

        $perMonitor = [];
        foreach ($rows as $row) {
            $name = $row['labels']['monitor_name'] ?? null;
            if ($name === null || ! isset($byName[$name])) {
                continue;
            }
            $perMonitor[$name][$row['labels']['window'] ?? ''] = $row['value'];
        }

        foreach ($perMonitor as $name => $windows) {
            $value = null;
            foreach ($preferred as $window) {
                if (isset($windows[$window])) {
                    $value = $windows[$window];
                    break;
                }
            }
            // Fall back to any single unlabelled/other value if no named window
            // is present ($windows is never empty — a key only exists once a row
            // for that monitor has been seen).
            $value ??= reset($windows);

            $byName[$name][$target] = $transform((float) $value);
        }
    }

    private function fetch(): string
    {
        try {
            // Uptime Kuma's documented convention: the API key is sent as the
            // basic-auth password; the username is ignored.
            $response = Http::withBasicAuth('', $this->apiKey)
                ->timeout(20)
                ->get(rtrim($this->baseUrl, '/').'/metrics');
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach the Uptime Kuma instance. Check the URL and that it is publicly reachable.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new IntegrationException('Uptime Kuma rejected the API key.');
        }

        if (! $response->successful()) {
            throw new IntegrationException("Uptime Kuma returned an unexpected response ({$response->status()}).");
        }

        return (string) $response->body();
    }

    /**
     * Minimal Prometheus text-exposition-format parser — just enough to read
     * gauge lines like `monitor_status{monitor_name="X"} 1`.
     *
     * @return array<string, array<int, array{labels: array<string, string>, value: float}>>
     */
    private function parseMetrics(string $body): array
    {
        $byMetric = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)\{([^}]*)\}\s+(-?[0-9.eE+-]+)\s*$/', $line, $m)) {
                continue;
            }

            $labels = [];
            if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)="((?:[^"\\\\]|\\\\.)*)"/', $m[2], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $pair) {
                    $labels[$pair[1]] = stripcslashes($pair[2]);
                }
            }

            $byMetric[$m[1]][] = ['labels' => $labels, 'value' => (float) $m[3]];
        }

        return $byMetric;
    }
}
