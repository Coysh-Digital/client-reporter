<?php

declare(strict_types=1);

namespace App\Integrations\UptimeKuma;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reads current monitor status from a self-hosted Uptime Kuma instance's
 * Prometheus-compatible `/metrics` endpoint. Kuma has no historical,
 * query-by-date-range API (unlike UptimeRobot/Better Uptime) — it only ever
 * exposes the current, point-in-time status of each monitor. {@see
 * MonitorsCollector} is responsible for polling this repeatedly and building
 * its own history from the samples.
 */
class UptimeKumaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * Current status of every monitor exposed by the API key, optionally
     * filtered to a set of monitor names.
     *
     * @param  array<int, string>  $onlyNames
     * @return array<int, array{name: string, url: ?string, status: int, response_time_ms: ?float}>
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
            ];
        }

        foreach ($metrics['monitor_response_time'] ?? [] as $row) {
            $name = $row['labels']['monitor_name'] ?? null;
            if ($name === null || ! isset($byName[$name])) {
                continue;
            }

            $byName[$name]['response_time_ms'] = $row['value'];
        }

        return array_values($byName);
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
