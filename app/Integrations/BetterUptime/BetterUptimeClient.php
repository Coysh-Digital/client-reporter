<?php

declare(strict_types=1);

namespace App\Integrations\BetterUptime;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Better Stack (Better Uptime) v2 API.
 */
class BetterUptimeClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://uptime.betterstack.com',
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function monitors(): array
    {
        $data = $this->request('api/v2/monitors')['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * SLA figures for one monitor over the period.
     *
     * @return array<string, mixed>
     */
    public function sla(string $monitorId, DateRange $range): array
    {
        $data = $this->request("api/v2/monitors/{$monitorId}/sla", [
            'from' => $range->start->toDateString(),
            'to' => $range->end->toDateString(),
        ]);

        return (array) (($data['data']['attributes'] ?? []) ?: []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function incidents(DateRange $range): array
    {
        $data = $this->request('api/v2/incidents', [
            'from' => $range->start->toDateString(),
            'to' => $range->end->toDateString(),
        ])['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function request(string $path, array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)->timeout(20)->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/'.$path, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Better Stack. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Better Stack rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Better Stack returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
