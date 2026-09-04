<?php

declare(strict_types=1);

namespace App\Integrations\Fathom;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Fathom Analytics API (v1 aggregations).
 */
class FathomClient
{
    private const BASE = 'https://api.usefathom.com/v1';

    public function __construct(
        private readonly string $token,
        private readonly string $siteId,
    ) {}

    /**
     * @param  array<string, scalar>  $extra
     * @return array<int, array<string, mixed>>
     */
    public function aggregations(DateRange $range, array $aggregates, array $extra = []): array
    {
        try {
            $response = Http::withToken($this->token)->timeout(20)->acceptJson()
                ->get(self::BASE.'/aggregations', array_merge([
                    'entity' => 'pageview',
                    'entity_id' => $this->siteId,
                    'aggregates' => implode(',', $aggregates),
                    'date_from' => $range->start->toDateString(),
                    'date_to' => $range->end->toDateString(),
                ], $extra));
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Fathom. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Fathom rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Fathom returned an error (HTTP '.$response->status().'). Check the site ID.');
        }

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
        try {
            $response = Http::withToken($this->token)->timeout(20)->acceptJson()
                ->get(self::BASE."/sites/{$this->siteId}/events", ['limit' => 100]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Fathom. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Fathom rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Fathom returned an error (HTTP '.$response->status().').');
        }

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
        try {
            $response = Http::withToken($this->token)->timeout(20)->acceptJson()
                ->get(self::BASE.'/aggregations', [
                    'entity' => 'event',
                    'site_id' => $this->siteId,
                    'entity_name' => $eventName,
                    'aggregates' => implode(',', $aggregates),
                    'date_from' => $range->start->toDateString(),
                    'date_to' => $range->end->toDateString(),
                ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Fathom. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Fathom rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Fathom returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }

    /**
     * Every site on the account, for the workspace connect flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sites(): array
    {
        try {
            $response = Http::withToken($this->token)->timeout(20)->acceptJson()->get(self::BASE.'/sites');
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Fathom. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Fathom rejected the API token.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Fathom returned an error (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $list = is_array($data) ? ($data['data'] ?? $data) : [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }
}
