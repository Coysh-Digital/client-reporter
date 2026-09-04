<?php

declare(strict_types=1);

namespace App\Integrations\GoogleSearchConsole;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Google Search Console search performance into search.* metrics plus a
 * snapshot of the top queries and pages for the period.
 */
class SearchAnalyticsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'search';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = GoogleSearchConsoleIntegration::clientFor($connection);

        $summary = $client->query($range)[0] ?? [];
        $clicks = (float) ($summary['clicks'] ?? 0);
        $impressions = (float) ($summary['impressions'] ?? 0);
        $ctr = (float) ($summary['ctr'] ?? 0) * 100;
        $position = (float) ($summary['position'] ?? 0);

        $mapRow = fn (array $row): array => [
            'label' => (string) (($row['keys'][0]) ?? ''),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => round((float) ($row['ctr'] ?? 0) * 100, 1),
            'position' => round((float) ($row['position'] ?? 0), 1),
        ];

        $topQueries = array_map($mapRow, $client->query($range, ['query'], 10));
        $topPages = array_map($mapRow, $client->query($range, ['page'], 10));

        // Daily clicks for a search-visibility trend line. GSC returns date rows
        // in no guaranteed order, so sort ascending for the chart. rowLimit 500
        // comfortably covers any report period.
        $daily = array_map(fn (array $row): array => [
            'date' => (string) (($row['keys'][0]) ?? ''),
            'value' => (int) ($row['clicks'] ?? 0),
        ], $client->query($range, ['date'], 500));
        usort($daily, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return CollectorResult::make()
            ->metric('search.clicks', $clicks)
            ->metric('search.impressions', $impressions)
            ->metric('search.ctr', round($ctr, 1), '%')
            ->metric('search.position', round($position, 1))
            ->snapshot([
                'top_queries' => $topQueries,
                'top_pages' => $topPages,
                'timeseries' => $daily,
            ]);
    }
}
