<?php

declare(strict_types=1);

namespace App\Integrations\Fathom;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Fathom analytics into the normalised analytics.* metric layer, plus a
 * snapshot with top pages, sources and a daily timeseries for charts.
 */
class FathomCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new FathomClient(
            (string) $connection->credential('api_token'),
            (string) $connection->setting('site_id'),
        );

        $totals = $client->aggregations($range, ['visits', 'uniques', 'pageviews', 'avg_duration', 'bounce_rate']);
        $row = $totals[0] ?? [];

        $topPages = array_map(fn (array $r): array => [
            'label' => (string) ($r['pathname'] ?? ''),
            'visitors' => (int) ($r['uniques'] ?? 0),
            'pageviews' => (int) ($r['pageviews'] ?? 0),
        ], $client->aggregations($range, ['uniques', 'pageviews'], ['field_grouping' => 'pathname', 'sort_by' => 'pageviews:desc', 'limit' => 5]));

        $sources = array_map(fn (array $r): array => [
            'label' => (string) ($r['referrer_hostname'] ?? 'Direct'),
            'visitors' => (int) ($r['uniques'] ?? 0),
        ], $client->aggregations($range, ['uniques'], ['field_grouping' => 'referrer_hostname', 'sort_by' => 'uniques:desc', 'limit' => 5]));

        $countries = array_map(fn (array $r): array => [
            'label' => (string) ($r['country_code'] ?? 'Unknown'),
            'visitors' => (int) ($r['uniques'] ?? 0),
        ], $this->safeAggregations($client, $range, ['uniques'], ['field_grouping' => 'country_code', 'sort_by' => 'uniques:desc', 'limit' => 8]));

        $devices = array_map(fn (array $r): array => [
            'label' => (string) ($r['device_type'] ?? 'Unknown'),
            'visitors' => (int) ($r['uniques'] ?? 0),
        ], $this->safeAggregations($client, $range, ['uniques'], ['field_grouping' => 'device_type', 'sort_by' => 'uniques:desc', 'limit' => 5]));

        $timeseries = array_map(fn (array $r): array => [
            'date' => (string) ($r['date'] ?? ''),
            'value' => (int) ($r['uniques'] ?? 0),
        ], $client->aggregations($range, ['uniques'], ['date_grouping' => 'day']));

        return CollectorResult::make()
            ->metric('analytics.visitors', (float) ($row['uniques'] ?? 0))
            ->metric('analytics.pageviews', (float) ($row['pageviews'] ?? 0))
            ->metric('analytics.visits', (float) ($row['visits'] ?? 0))
            ->metric('analytics.bounce_rate', (float) ($row['bounce_rate'] ?? 0), '%')
            ->metric('analytics.visit_duration', (float) ($row['avg_duration'] ?? 0), 'seconds')
            ->snapshot([
                'provider' => 'Fathom',
                'top_pages' => $topPages,
                'sources' => $sources,
                'countries' => $countries,
                'devices' => $devices,
                'events' => $this->events($client, $range),
                'timeseries' => $timeseries,
            ]);
    }

    /**
     * Fathom has no "group by event name" query — event definitions are
     * listed once (not period-scoped), then each is queried individually for
     * this period's conversion count. Events with none in the period are
     * omitted rather than shown as zero.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function events(FathomClient $client, DateRange $range): array
    {
        try {
            $names = $client->eventNames();
        } catch (IntegrationException) {
            return [];
        }

        $events = [];
        foreach ($names as $name) {
            try {
                $row = $client->eventAggregation($range, $name, ['conversions'])[0] ?? [];
            } catch (IntegrationException) {
                continue;
            }

            $count = (int) ($row['conversions'] ?? 0);
            if ($count > 0) {
                $events[] = ['label' => $name, 'count' => $count];
            }
        }

        usort($events, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $events;
    }

    /**
     * @param  array<int, string>  $aggregates
     * @param  array<string, scalar>  $extra
     * @return array<int, array<string, mixed>>
     */
    private function safeAggregations(FathomClient $client, DateRange $range, array $aggregates, array $extra): array
    {
        try {
            return $client->aggregations($range, $aggregates, $extra);
        } catch (IntegrationException) {
            return [];
        }
    }
}
