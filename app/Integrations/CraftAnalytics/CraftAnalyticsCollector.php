<?php

declare(strict_types=1);

namespace App\Integrations\CraftAnalytics;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Pulls Craft Analytics into the normalised analytics.* metric layer, plus a
 * snapshot with top pages, sources, devices, countries and a daily timeseries —
 * the same shape the other analytics providers emit, so it feeds every Analytics
 * report block and the site-page charts without any provider-specific rendering.
 */
class CraftAnalyticsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'summary';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = (new CraftAnalyticsIntegration)->client($connection);

        $data = $client->get('report', [
            'from' => $range->start->toDateString(),
            'to' => $range->end->toDateString(),
        ]);

        /** @var array<string, mixed> $metrics */
        $metrics = $data['metrics'] ?? [];
        $value = fn (string $key): float => (float) ($metrics[$key] ?? 0);

        return CollectorResult::make()
            ->metric('analytics.visitors', $value('visitors'))
            ->metric('analytics.pageviews', $value('pageviews'))
            ->metric('analytics.visits', $value('visits'))
            ->metric('analytics.bounce_rate', $value('bounce_rate'), '%')
            ->metric('analytics.visit_duration', $value('visit_duration'), 'seconds')
            ->snapshot([
                'provider' => 'Craft Analytics',
                'top_pages' => $this->rows($data['top_pages'] ?? [], ['label', 'visitors', 'pageviews']),
                'sources' => $this->rows($data['sources'] ?? [], ['label', 'visitors']),
                'countries' => $this->rows($data['countries'] ?? [], ['label', 'visitors']),
                'devices' => $this->rows($data['devices'] ?? [], ['label', 'visitors']),
                'events' => $this->rows($data['events'] ?? [], ['label', 'count']),
                'timeseries' => $this->timeseries($data['timeseries'] ?? []),
            ]);
    }

    /**
     * Whitelist and coerce a list of rows to the keys we render, dropping
     * anything unexpected so a malformed row never reaches a report.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($keys as $key) {
                $clean[$key] = $key === 'label'
                    ? (string) ($row[$key] ?? '')
                    : (int) ($row[$key] ?? 0);
            }
            $out[] = $clean;
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, value: int}>
     */
    private function timeseries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        $out = [];
        foreach ($series as $point) {
            if (! is_array($point)) {
                continue;
            }
            $out[] = ['date' => (string) ($point['date'] ?? ''), 'value' => (int) ($point['value'] ?? 0)];
        }

        return $out;
    }
}
