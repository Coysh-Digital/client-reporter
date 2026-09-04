<?php

declare(strict_types=1);

namespace App\Integrations\Plausible;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Plausible analytics into the normalised analytics.* metric layer,
 * plus a snapshot with top pages, sources and a daily timeseries for charts.
 */
class PlausibleCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new PlausibleClient(
            (string) $connection->credential('api_token'),
            (string) $connection->setting('site_id'),
            (string) ($connection->setting('base_url') ?: 'https://plausible.io'),
        );

        $aggregate = $client->aggregate($range, ['visitors', 'pageviews', 'visits', 'bounce_rate', 'visit_duration']);
        $value = fn (string $key): float => (float) ($aggregate[$key]['value'] ?? 0);

        $topPages = array_map(fn (array $row): array => [
            'label' => (string) ($row['page'] ?? ''),
            'visitors' => (int) ($row['visitors'] ?? 0),
            'pageviews' => (int) ($row['pageviews'] ?? 0),
        ], $client->breakdown($range, 'event:page', ['visitors', 'pageviews']));

        $sources = array_map(fn (array $row): array => [
            'label' => (string) ($row['source'] ?? 'Direct'),
            'visitors' => (int) ($row['visitors'] ?? 0),
        ], $client->breakdown($range, 'visit:source', ['visitors']));

        $countries = array_map(fn (array $row): array => [
            'label' => (string) ($row['country'] ?? 'Unknown'),
            'visitors' => (int) ($row['visitors'] ?? 0),
        ], $this->safeBreakdown($client, $range, 'visit:country', ['visitors']));

        $devices = array_map(fn (array $row): array => [
            'label' => (string) ($row['device'] ?? 'Unknown'),
            'visitors' => (int) ($row['visitors'] ?? 0),
        ], $this->safeBreakdown($client, $range, 'visit:device', ['visitors']));

        // 'event:name' breaks down by custom event/goal name — only meaningful
        // once at least one is configured on the site, and some plans/older
        // self-hosted versions may reject the property entirely.
        $events = array_map(fn (array $row): array => [
            'label' => (string) ($row['name'] ?? 'Event'),
            'count' => (int) ($row['visitors'] ?? 0),
        ], $this->safeBreakdown($client, $range, 'event:name', ['visitors']));

        $timeseries = array_map(fn (array $row): array => [
            'date' => (string) ($row['date'] ?? ''),
            'value' => (int) ($row['visitors'] ?? 0),
        ], $client->timeseries($range, 'visitors'));

        return CollectorResult::make()
            ->metric('analytics.visitors', $value('visitors'))
            ->metric('analytics.pageviews', $value('pageviews'))
            ->metric('analytics.visits', $value('visits'))
            ->metric('analytics.bounce_rate', $value('bounce_rate'), '%')
            ->metric('analytics.visit_duration', $value('visit_duration'), 'seconds')
            ->snapshot([
                'provider' => 'Plausible',
                'top_pages' => $topPages,
                'sources' => $sources,
                'countries' => $countries,
                'devices' => $devices,
                'events' => $events,
                'timeseries' => $timeseries,
            ]);
    }

    /**
     * @param  array<int, string>  $metrics
     * @return array<int, array<string, mixed>>
     */
    private function safeBreakdown(PlausibleClient $client, DateRange $range, string $property, array $metrics): array
    {
        try {
            return $client->breakdown($range, $property, $metrics);
        } catch (IntegrationException) {
            return [];
        }
    }
}
