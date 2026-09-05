<?php

declare(strict_types=1);

namespace App\Integrations\Umami;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the Umami analytics API (Umami Cloud or a self-hosted
 * instance reachable with an API key). Time ranges are millisecond epochs.
 */
class UmamiClient extends AbstractHttpClient
{
    public function __construct(
        private readonly string $instanceUrl,
        private readonly string $apiKey,
        private readonly string $websiteId,
    ) {}

    protected function provider(): string
    {
        return 'Umami';
    }

    protected function baseUrl(): ?string
    {
        return $this->instanceUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(DateRange $range): array
    {
        return $this->request("websites/{$this->websiteId}/stats", $range);
    }

    /**
     * @return array<int, array{x: mixed, y: mixed}>
     */
    public function metrics(DateRange $range, string $type, int $limit = 5): array
    {
        $data = $this->request("websites/{$this->websiteId}/metrics", $range, ['type' => $type, 'limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function pageviews(DateRange $range): array
    {
        return $this->request("websites/{$this->websiteId}/pageviews", $range, ['unit' => 'day', 'timezone' => 'UTC']);
    }

    /**
     * Every website on the account, for the workspace connect flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function websites(): array
    {
        $data = $this->request('websites', null);
        $list = $data['data'] ?? $data;

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * @param  array<string, scalar>  $extra
     * @return array<mixed>
     */
    private function request(string $path, ?DateRange $range, array $extra = []): array
    {
        $params = $extra;
        if ($range !== null) {
            $params['startAt'] = $range->start->timestamp * 1000;
            $params['endAt'] = $range->end->timestamp * 1000;
        }

        $response = $this->get($path, $params, fn (PendingRequest $r): PendingRequest => $r->withHeaders(['x-umami-api-key' => $this->apiKey]));

        $this->guard($response, [
            401 => 'Umami rejected the API key.',
            403 => 'Umami rejected the API key.',
        ] + ($response->successful() || in_array($response->status(), [401, 403], true) ? [] : [
            $response->status() => 'Umami returned an error (HTTP '.$response->status().'). Check the website ID.',
        ]));

        return (array) $response->json();
    }
}
