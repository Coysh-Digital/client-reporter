<?php

declare(strict_types=1);

namespace App\Integrations\Umami;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Umami analytics into the shared analytics.* metric layer plus a
 * snapshot (top pages, sources, daily timeseries), matching every analytics
 * provider so the generic analytics blocks render it unchanged.
 */
class UmamiCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new UmamiClient(
            (string) ($connection->setting('base_url') ?: 'https://api.umami.is/v1'),
            (string) $connection->credential('api_key'),
            (string) $connection->setting('website_id'),
        );

        $stats = $client->stats($range);
        $val = fn (string $key): float => (float) ($stats[$key]['value'] ?? 0);

        $visits = $val('visits');
        $bounces = $val('bounces');
        $totalTime = $val('totaltime');
        $bounceRate = $visits > 0 ? round($bounces / $visits * 100, 1) : 0.0;
        $avgDuration = $visits > 0 ? round($totalTime / $visits) : 0.0;

        $topPages = array_map(fn (array $row): array => [
            'label' => (string) ($row['x'] ?? ''),
            'visitors' => (int) ($row['y'] ?? 0),
            'pageviews' => (int) ($row['y'] ?? 0),
        ], $client->metrics($range, 'url'));

        $sources = array_map(fn (array $row): array => [
            'label' => (string) ($row['x'] ?? '') ?: 'Direct',
            'visitors' => (int) ($row['y'] ?? 0),
        ], $client->metrics($range, 'referrer'));

        $countries = array_map(fn (array $row): array => [
            'label' => (string) ($row['x'] ?? 'Unknown'),
            'visitors' => (int) ($row['y'] ?? 0),
        ], $client->metrics($range, 'country'));

        $devices = array_map(fn (array $row): array => [
            'label' => ucfirst((string) ($row['x'] ?? 'Unknown')),
            'visitors' => (int) ($row['y'] ?? 0),
        ], $client->metrics($range, 'device'));

        $events = array_map(fn (array $row): array => [
            'label' => (string) ($row['x'] ?? 'Event'),
            'count' => (int) ($row['y'] ?? 0),
        ], $client->metrics($range, 'event'));

        $series = array_map(fn (array $row): array => [
            'date' => (string) ($row['x'] ?? ''),
            'value' => (int) ($row['y'] ?? 0),
        ], (array) ($client->pageviews($range)['sessions'] ?? []));

        return CollectorResult::make()
            ->metric('analytics.visitors', $val('visitors'))
            ->metric('analytics.pageviews', $val('pageviews'))
            ->metric('analytics.visits', $visits)
            ->metric('analytics.bounce_rate', $bounceRate, '%')
            ->metric('analytics.visit_duration', $avgDuration, 'seconds')
            ->snapshot([
                'provider' => 'Umami',
                'top_pages' => $topPages,
                'sources' => $sources,
                'countries' => $countries,
                'devices' => $devices,
                'events' => $events,
                'timeseries' => $series,
            ]);
    }
}
