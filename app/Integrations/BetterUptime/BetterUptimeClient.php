<?php

declare(strict_types=1);

namespace App\Integrations\BetterUptime;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the Better Stack (Better Uptime) v2 API.
 */
class BetterUptimeClient extends AbstractHttpClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $apiBase = 'https://uptime.betterstack.com',
    ) {}

    protected function provider(): string
    {
        return 'Better Stack';
    }

    protected function baseUrl(): ?string
    {
        return $this->apiBase;
    }

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
        $response = $this->get($path, $params, fn (PendingRequest $r): PendingRequest => $r->withToken($this->token));

        $this->guard($response, [
            401 => 'Better Stack rejected the API token.',
            403 => 'Better Stack rejected the API token.',
        ]);

        return (array) $response->json();
    }
}
