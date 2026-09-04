<?php

declare(strict_types=1);

namespace App\Integrations\Matomo;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Matomo analytics into the shared analytics.* metric layer plus a
 * snapshot (top pages, sources, daily timeseries) — the same shape every
 * analytics provider emits, so the generic analytics blocks render it as-is.
 */
class MatomoCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new MatomoClient(
            (string) $connection->setting('base_url'),
            (string) $connection->credential('token'),
            (string) $connection->setting('site_id'),
        );

        $summary = $client->visitsSummary($range);
        $actions = $client->actions($range);

        $visitors = (float) ($summary['nb_uniq_visitors'] ?? 0);
        $visits = (float) ($summary['nb_visits'] ?? 0);
        $pageviews = (float) ($actions['nb_pageviews'] ?? $summary['nb_actions'] ?? 0);
        $bounceRate = (float) str_replace('%', '', (string) ($summary['bounce_rate'] ?? '0'));
        $duration = (float) ($summary['avg_time_on_site'] ?? 0);

        $topPages = array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? ''),
            'visitors' => (int) ($row['nb_visits'] ?? 0),
            'pageviews' => (int) ($row['nb_hits'] ?? 0),
        ], $client->pageUrls($range));

        $sources = array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? 'Direct'),
            'visitors' => (int) ($row['nb_visits'] ?? 0),
        ], $client->referrerTypes($range));

        $countries = array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? 'Unknown'),
            'visitors' => (int) ($row['nb_visits'] ?? 0),
        ], $this->safeCall(fn () => $client->countries($range)));

        $devices = array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? 'Unknown'),
            'visitors' => (int) ($row['nb_visits'] ?? 0),
        ], $this->safeCall(fn () => $client->deviceTypes($range)));

        $events = array_map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? 'Event'),
            'count' => (int) ($row['nb_events'] ?? 0),
        ], $this->safeCall(fn () => $client->events($range)));

        $timeseries = [];
        foreach ($client->visitorsSeries($range) as $date => $row) {
            $timeseries[] = [
                'date' => (string) $date,
                'value' => (int) ($row['nb_uniq_visitors'] ?? 0),
            ];
        }

        return CollectorResult::make()
            ->metric('analytics.visitors', $visitors)
            ->metric('analytics.pageviews', $pageviews)
            ->metric('analytics.visits', $visits)
            ->metric('analytics.bounce_rate', $bounceRate, '%')
            ->metric('analytics.visit_duration', $duration, 'seconds')
            ->snapshot([
                'provider' => 'Matomo',
                'top_pages' => $topPages,
                'sources' => $sources,
                'countries' => $countries,
                'devices' => $devices,
                'events' => $events,
                'timeseries' => $timeseries,
            ]);
    }

    /**
     * Some of these API methods live in optional Matomo plugins that may be
     * disabled on a given instance — degrade to no data rather than failing
     * the whole collection over one missing breakdown.
     *
     * @param  callable(): array<int, array<string, mixed>>  $call
     * @return array<int, array<string, mixed>>
     */
    private function safeCall(callable $call): array
    {
        try {
            return $call();
        } catch (IntegrationException) {
            return [];
        }
    }
}
