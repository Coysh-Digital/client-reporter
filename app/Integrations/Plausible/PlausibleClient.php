<?php

declare(strict_types=1);

namespace App\Integrations\Plausible;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Plausible Stats API (works with plausible.io or a
 * self-hosted instance).
 */
class PlausibleClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $siteId,
        private readonly string $baseUrl = 'https://plausible.io',
    ) {}

    /**
     * @param  array<int, string>  $metrics
     * @return array<string, array{value: mixed}>
     */
    public function aggregate(DateRange $range, array $metrics): array
    {
        $data = $this->request('api/v1/stats/aggregate', [
            'metrics' => implode(',', $metrics),
        ], $range);

        return $data['results'] ?? [];
    }

    /**
     * @param  array<int, string>  $metrics
     * @return array<int, array<string, mixed>>
     */
    public function breakdown(DateRange $range, string $property, array $metrics, int $limit = 5): array
    {
        $data = $this->request('api/v1/stats/breakdown', [
            'property' => $property,
            'metrics' => implode(',', $metrics),
            'limit' => $limit,
        ], $range);

        return $data['results'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timeseries(DateRange $range, string $metric = 'visitors'): array
    {
        $data = $this->request('api/v1/stats/timeseries', ['metrics' => $metric], $range);

        return $data['results'] ?? [];
    }

    /**
     * Every site on the account, for the workspace connect flow. Requires the
     * API key to have "Site provisioning" access, not just Stats.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sites(): array
    {
        try {
            $response = $this->http()->get(rtrim($this->baseUrl, '/').'/api/v1/sites');
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Plausible. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Plausible rejected the request. The API key needs "Site provisioning" access to list sites.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Plausible returned an error (HTTP '.$response->status().').');
        }

        $sites = $response->json('sites', []);

        return is_array($sites) ? $sites : [];
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function request(string $path, array $params, DateRange $range): array
    {
        try {
            $response = $this->http()->get(rtrim($this->baseUrl, '/').'/'.$path, array_merge([
                'site_id' => $this->siteId,
                'period' => 'custom',
                'date' => $range->start->toDateString().','.$range->end->toDateString(),
            ], $params));
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Plausible. Please try again shortly.');
        }

        if ($response->status() === 401) {
            throw new IntegrationException('Plausible rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Plausible returned an error (HTTP '.$response->status().'). Check the site ID.');
        }

        return (array) $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->timeout(20)->acceptJson();
    }
}
