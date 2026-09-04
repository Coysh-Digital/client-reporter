<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAnalytics;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Google Analytics 4 data into the normalised analytics.* metric layer,
 * plus a snapshot with top pages, sources and a daily timeseries.
 */
class GoogleAnalyticsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = GoogleAnalyticsIntegration::clientFor($connection);

        $totals = $client->runReport($range, ['activeUsers', 'screenPageViews', 'sessions', 'bounceRate', 'averageSessionDuration']);

        $pages = $client->runReport($range, ['screenPageViews', 'activeUsers'], ['pagePath'], 8);
        $sources = $client->runReport($range, ['activeUsers'], ['sessionSource'], 8);
        $series = $client->runReport($range, ['activeUsers'], ['date'], 400);
        $countries = $client->runReport($range, ['activeUsers'], ['country'], 8);
        $devices = $client->runReport($range, ['activeUsers'], ['deviceCategory'], 5);
        $events = $client->runReport($range, ['eventCount'], ['eventName'], 10);

        $metric = fn (string $name): float => (float) $this->readMetric($totals, $name);

        return CollectorResult::make()
            ->metric('analytics.visitors', $metric('activeUsers'))
            ->metric('analytics.pageviews', $metric('screenPageViews'))
            ->metric('analytics.visits', $metric('sessions'))
            ->metric('analytics.bounce_rate', $metric('bounceRate') * 100, '%')
            ->metric('analytics.visit_duration', $metric('averageSessionDuration'), 'seconds')
            ->snapshot([
                'provider' => 'Google Analytics',
                'top_pages' => $this->rows($pages, fn (array $d, array $m): array => [
                    'label' => $d[0] ?? '/',
                    'pageviews' => (int) ($m[0] ?? 0),
                    'visitors' => (int) ($m[1] ?? 0),
                ]),
                'sources' => $this->rows($sources, fn (array $d, array $m): array => [
                    'label' => ($d[0] ?? '') === '(direct)' ? 'Direct' : ($d[0] ?? 'Direct'),
                    'visitors' => (int) ($m[0] ?? 0),
                ]),
                'timeseries' => $this->rows($series, fn (array $d, array $m): array => [
                    'date' => $this->formatGaDate($d[0] ?? ''),
                    'value' => (int) ($m[0] ?? 0),
                ]),
                'countries' => $this->rows($countries, fn (array $d, array $m): array => [
                    'label' => $d[0] ?? 'Unknown',
                    'visitors' => (int) ($m[0] ?? 0),
                ]),
                'devices' => $this->rows($devices, fn (array $d, array $m): array => [
                    'label' => ucfirst($d[0] ?? 'Unknown'),
                    'visitors' => (int) ($m[0] ?? 0),
                ]),
                'events' => $this->rows($events, fn (array $d, array $m): array => [
                    'label' => $d[0] ?? 'Event',
                    'count' => (int) ($m[0] ?? 0),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function readMetric(array $report, string $name): float
    {
        $headers = array_column($report['metricHeaders'] ?? [], 'name');
        $index = array_search($name, $headers, true);

        if ($index === false) {
            return 0.0;
        }

        return (float) ($report['rows'][0]['metricValues'][$index]['value'] ?? 0);
    }

    /**
     * Map GA rows through a mapper receiving [dimensionValues, metricValues].
     *
     * @param  array<string, mixed>  $report
     * @param  callable(array<int, string>, array<int, string>): array<string, mixed>  $mapper
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $report, callable $mapper): array
    {
        return array_map(function (array $row) use ($mapper): array {
            $dimensions = array_column($row['dimensionValues'] ?? [], 'value');
            $metrics = array_column($row['metricValues'] ?? [], 'value');

            return $mapper($dimensions, $metrics);
        }, $report['rows'] ?? []);
    }

    private function formatGaDate(string $ga): string
    {
        // GA4 daily date dimension is "YYYYMMDD".
        return strlen($ga) === 8
            ? substr($ga, 0, 4).'-'.substr($ga, 4, 2).'-'.substr($ga, 6, 2)
            : $ga;
    }
}
