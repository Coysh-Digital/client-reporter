<?php

declare(strict_types=1);

namespace App\Integrations\Fathom;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Read-only client for the Fathom Analytics API (v1 aggregations).
 */
class FathomClient extends AbstractHttpClient
{
    private const BASE = 'https://api.usefathom.com/v1';

    public function __construct(
        private readonly string $token,
        private readonly string $siteId,
    ) {}

    protected function provider(): string
    {
        return 'Fathom';
    }

    /**
     * @param  array<string, scalar>  $extra
     * @return array<int, array<string, mixed>>
     */
    public function aggregations(DateRange $range, array $aggregates, array $extra = []): array
    {
        $response = $this->request('/aggregations', array_merge([
            'entity' => 'pageview',
            'entity_id' => $this->siteId,
            'aggregates' => implode(',', $aggregates),
            'date_from' => $range->start->toDateString(),
            'date_to' => $range->end->toDateString(),
        ], $extra), 'Fathom returned an error (HTTP :status). Check the site ID.');

        return (array) $response->json();
    }

    /**
     * Every event (goal) name defined on the site — not period-scoped, since
     * List Events has no date filter. Query {@see eventAggregation()} per
     * name for a period's conversion count.
     *
     * @return array<int, string>
     */
    public function eventNames(): array
    {
        $response = $this->request("/sites/{$this->siteId}/events", ['limit' => 100]);

        return array_values(array_filter(array_map(
            fn (array $event): string => (string) ($event['name'] ?? ''),
            (array) ($response->json('data') ?? []),
        )));
    }

    /**
     * Conversion aggregate for one named event over the period. Fathom has no
     * "group by event name" query — each event must be queried individually.
     *
     * @param  array<int, string>  $aggregates
     * @return array<int, array<string, mixed>>
     */
    public function eventAggregation(DateRange $range, string $eventName, array $aggregates): array
    {
        $response = $this->request('/aggregations', [
            'entity' => 'event',
            'site_id' => $this->siteId,
            'entity_name' => $eventName,
            'aggregates' => implode(',', $aggregates),
            'date_from' => $range->start->toDateString(),
            'date_to' => $range->end->toDateString(),
        ]);

        return (array) $response->json();
    }

    /**
     * Every site on the account, for the workspace connect flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sites(): array
    {
        $data = $this->request('/sites')->json();
        $list = is_array($data) ? ($data['data'] ?? $data) : [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $path, array $query = [], ?string $failure = null): Response
    {
        $response = $this->get(self::BASE.$path, $query, fn (PendingRequest $r): PendingRequest => $r->withToken($this->token));

        $messages = [
            401 => 'Fathom rejected the API token.',
            403 => 'Fathom rejected the API token.',
        ];

        if ($failure !== null && ! $response->successful()) {
            $messages[$response->status()] ??= str_replace(':status', (string) $response->status(), $failure);
        }

        return $this->guard($response, $messages);
    }
}
